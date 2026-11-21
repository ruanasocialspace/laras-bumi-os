"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Loading, Notice } from "@/components/ui/feedback";
import { useActor } from "@/features/session/workspace";
import { contractStatus, customerStatus, DataError, Editor, options, Records, useRecords } from "./shared";
import type { Category, Contract, Customer } from "./types";

const linkStyle = "font-semibold text-primary hover:underline";

export function CustomerDirectory() {
  const actor = useActor();
  if (!actor.permissions.includes("customers.read")) return <Notice>Anda tidak memiliki izin untuk melihat pelanggan.</Notice>;
  return <Directory kind="customers" />;
}

export function ContractDirectory() {
  const actor = useActor();
  if (!actor.permissions.includes("contracts.read")) return <Notice>Anda tidak memiliki izin untuk melihat kontrak.</Notice>;
  return <Directory kind="contracts" />;
}

function Directory({ kind }: { kind: "customers" | "contracts" }) {
  const actor = useActor();
  const router = useRouter();
  const customers = kind === "customers";
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("");
  const [category, setCategory] = useState("");
  const [filter, setFilter] = useState("");
  const [editing, setEditing] = useState<"customer" | "category" | null>(null);
  const [message, setMessage] = useState("");
  const categories = useRecords<{ data: Category[] }>(customers ? "/customer-categories" : "");
  const choices = (categories.data?.data ?? []).map(({ id, name }) => ({ value: id, label: name }));
  const labels = customers ? customerStatus : contractStatus;

  return <div className="space-y-5">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-3xl font-bold tracking-tight">{customers ? "Pelanggan" : "Kontrak"}</h1>
      <div className="flex flex-wrap items-center gap-2">
        {customers && actor.permissions.includes("customers.categories.manage") && <Button variant="outline" onClick={() => setEditing("category")}>Tambah kategori</Button>}
        {customers && actor.permissions.includes("customers.write") && <Button disabled={choices.length === 0} onClick={() => setEditing("customer")}>Tambah pelanggan</Button>}
        {!customers && actor.permissions.includes("contracts.write") && <Link className={linkStyle} href="/customers">Pilih pelanggan untuk membuat kontrak</Link>}
      </div>
    </div>
    {message && <Notice success>{message}</Notice>}
    {customers && (categories.error ? <DataError error={categories.error} onRetry={categories.refresh} /> : !categories.data && <Loading>Memuat kategori…</Loading>)}
    {editing === "category" && <Editor<Category> key={editing} title="Kategori pelanggan baru" path="/customer-categories" fields={[
      { name: "code", label: "Kode kategori", required: true, max: 32 },
      { name: "name", label: "Nama kategori", required: true, max: 120 },
    ]} onSaved={() => { setEditing(null); setMessage("Kategori disimpan."); categories.refresh(); }} onCancel={() => setEditing(null)} />}
    {editing === "customer" && <Editor<Customer> key={editing} title="Pelanggan baru" path="/customers" fields={[
      { name: "code", label: "Kode pelanggan", required: true, max: 32 },
      { name: "name", label: "Nama pelanggan", required: true, max: 160 },
      { name: "category_id", label: "Kategori pelanggan", options: choices, required: true },
    ]} onSaved={(customer) => router.push("/customers/" + customer.id)} onCancel={() => setEditing(null)} />}
    <section className="panel space-y-5" aria-label={customers ? "Daftar pelanggan" : "Daftar kontrak"}>
      <form className="flex flex-wrap items-end gap-3" onSubmit={(event) => {
        event.preventDefault();
        const params = new URLSearchParams();
        if (query.trim()) params.set("q", query.trim());
        if (status) params.set("status", status);
        if (customers && category) params.set("category_id", category);
        setFilter(params.toString());
      }}>
        <label className="min-w-52 flex-1 text-sm font-semibold">Nama atau kode
          <input className="control mt-1" type="search" maxLength={160} value={query} onChange={(event) => setQuery(event.target.value)} />
        </label>
        <label className="text-sm font-semibold">Status
          <select className="control mt-1" value={status} onChange={(event) => setStatus(event.target.value)}>
            <option value="">Semua status</option>
            {options(labels).map(({ value, label }) => <option key={value} value={value}>{label}</option>)}
          </select>
        </label>
        {customers && <label className="text-sm font-semibold">Kategori
          <select className="control mt-1" value={category} onChange={(event) => setCategory(event.target.value)}>
            <option value="">Semua kategori</option>
            {choices.map(({ value, label }) => <option key={value} value={value}>{label}</option>)}
          </select>
        </label>}
        <Button type="submit" variant="outline">Cari</Button>
      </form>
      <Records<Customer | Contract> key={filter} path={"/" + kind + (filter ? "?" + filter : "")}
        empty={customers ? "Pelanggan tidak ditemukan." : "Kontrak tidak ditemukan."} render={(row) => (
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div><Link className={linkStyle} href={"/" + kind + "/" + row.id}>{row.name}</Link>
              <p className="mt-1 text-sm text-subtle">{row.code}{"category_id" in row && choices.find((item) => item.value === row.category_id)
                ? " · " + choices.find((item) => item.value === row.category_id)?.label : ""}</p></div>
            <span className="rounded-full bg-muted px-3 py-1 text-xs font-semibold">{labels["effective_status" in row ? row.effective_status : row.status]}</span>
          </div>
        )} />
    </section>
  </div>;
}
