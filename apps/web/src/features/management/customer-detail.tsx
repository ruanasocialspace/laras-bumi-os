"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { CustomerPickupHistory } from "@/features/collection/dispatch";
import { Loading, Notice } from "@/components/ui/feedback";
import { useActor } from "@/features/session/workspace";
import { contractStatus, customerStatus, DataError, Editor, options, Records, useRecords, type Field, type Values } from "./shared";
import type { Billing, Category, Contact, Contract, Customer, Location } from "./types";

const textLink = "text-primary font-semibold hover:underline";
const contractFields: Field[] = [
  { name: "name", label: "Nama kontrak", max: 160, required: true },
  { name: "starts_on", label: "Mulai kontrak", type: "date", required: true },
  { name: "ends_on", label: "Hari terakhir kontrak", type: "date" },
];
export const editableContractFields = contractFields;

const contactFields: Field[] = [
  { name: "name", label: "Nama kontak", max: 120, required: true },
  { name: "phone", label: "Telepon", type: "tel", max: 32 },
  { name: "email", label: "Email kontak", type: "email", max: 254 },
];
const locationFields: Field[] = [
  { name: "name", label: "Nama lokasi", max: 120, required: true },
  { name: "address", label: "Alamat penjemputan", type: "textarea", max: 500, required: true },
  { name: "latitude", label: "Lintang", inputMode: "decimal", max: 11 },
  { name: "longitude", label: "Bujur", inputMode: "decimal", max: 11 },
];
const billingFields: Field[] = [
  { name: "billing_name", label: "Nama penagihan", max: 160, required: true },
  { name: "address", label: "Alamat penagihan", type: "textarea", max: 500, required: true },
  { name: "email", label: "Email penagihan", type: "email", max: 254 },
  { name: "payment_term_days", label: "Jatuh tempo dalam hari", type: "number", required: true },
];

export function CustomerDetail({ id }: { id: string }) {
  const actor = useActor();
  return actor.permissions.includes("customers.read")
    ? <CustomerProfile id={id} /> : <Notice>Anda tidak memiliki izin untuk melihat pelanggan.</Notice>;
}

function CustomerProfile({ id }: { id: string }) {
  const actor = useActor();
  const router = useRouter();
  const path = "/customers/" + id;
  const customerQuery = useRecords<{ data: Customer }>(path);
  const categories = useRecords<{ data: Category[] }>("/customer-categories");
  const [editing, setEditing] = useState(false);
  const [newContract, setNewContract] = useState(false);
  const [message, setMessage] = useState("");

  if (customerQuery.error) return <DataError error={customerQuery.error} onRetry={customerQuery.refresh} />;
  if (categories.error) return <DataError error={categories.error} onRetry={categories.refresh} />;
  const customer = customerQuery.data?.data;
  if (!customer || !categories.data) return <Loading />;

  const editable = customer.status !== "archived" && actor.permissions.includes("customers.write");
  const categoryOptions = categories.data.data.map(({ id, name }) => ({ value: id, label: name }));
  const category = categoryOptions.find(({ value }) => value === customer.category_id)?.label;

  return <div className="space-y-5">
    <Link className={textLink} href="/customers">Kembali ke pelanggan</Link>
    <header className="panel">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div><p className="text-sm text-subtle">{customer.code} · {category}</p>
          <h1 className="mt-1 text-3xl font-bold tracking-tight">{customer.name}</h1>
          <p className="mt-2 text-sm font-semibold">{customerStatus[customer.status]}</p></div>
        {editable && <Button variant="outline" onClick={() => { setMessage(""); setEditing(true); }}>Ubah pelanggan</Button>}
      </div>
    </header>
    {message && <Notice success>{message}</Notice>}
    {editing && <Editor<Customer> title="Ubah pelanggan" path={path} method="PATCH" fields={[
      { name: "name", label: "Nama pelanggan", required: true, max: 160 },
      { name: "category_id", label: "Kategori pelanggan", options: categoryOptions, required: true },
      { name: "status", label: "Status pelanggan", options: options(customerStatus), required: true },
    ]} initial={customer} onSaved={() => { setEditing(false); setMessage("Pelanggan disimpan."); customerQuery.refresh(); }}
    onCancel={() => setEditing(false)} />}
    <div className="grid items-start gap-5 xl:grid-cols-2">
      <Related<Contact> title="Kontak" singular="kontak" path={path + "/contacts"} fields={contactFields} editable={editable}
        initial={(contact) => ({ name: contact.name, phone: contact.phone ?? "", email: contact.email ?? "" })}
        description={(contact) => [contact.phone, contact.email].filter(Boolean).join(" · ")} />
      <Related<Location> title="Lokasi penjemputan" singular="lokasi" path={path + "/locations"} fields={locationFields} editable={editable}
        initial={(location) => ({ name: location.name, address: location.address, latitude: location.latitude ?? "", longitude: location.longitude ?? "" })}
        description={(location) => location.address + (location.latitude !== null ? " · " + location.latitude + ", " + location.longitude : "")} />
    </div>
    {actor.permissions.includes("collections.read") && <CustomerPickupHistory customerId={id} />}
    {actor.permissions.includes("finance.read") && <BillingPanel path={path + "/billing-profile"}
      editable={customer.status !== "archived" && actor.permissions.includes("finance.bill")} />}
    {actor.permissions.includes("contracts.read") && <section className="panel space-y-4" aria-label="Kontrak pelanggan">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">Kontrak pelanggan</h2>
        {customer.status === "active" && actor.permissions.includes("contracts.write")
          && <Button onClick={() => setNewContract(true)}>Tambah kontrak</Button>}
      </div>
      {newContract && <Editor<Contract> title="Kontrak baru" path="/contracts"
        fields={[{ name: "code", label: "Kode kontrak", max: 32, required: true }, ...contractFields]}
        normalize={(values) => ({ ...values, customer_id: id })}
        onSaved={(contract) => router.push("/contracts/" + contract.id)} onCancel={() => setNewContract(false)} />}
      <Records<Contract> path={"/contracts?customer_id=" + id} empty="Belum ada kontrak."
        render={(contract) => <div><Link className={textLink} href={"/contracts/" + contract.id}>{contract.name}</Link>
          <p className="text-sm text-subtle">{contract.code} · {contractStatus[contract.effective_status]}</p></div>} />
    </section>}
  </div>;
}

function Related<T extends { id: string; name: string }>({ title, singular, path, fields, editable, initial, description }: {
  title: string; singular: string; path: string; fields: Field[]; editable: boolean;
  initial: (row: T) => Values; description: (row: T) => string;
}) {
  const [edit, setEdit] = useState<T | "new" | null>(null);
  const [revision, setRevision] = useState(0);

  return <section className="panel space-y-4" aria-label={title}>
    <div className="flex flex-wrap items-center justify-between gap-2">
      <h2 className="text-lg font-semibold">{title}</h2>
      {editable && <Button variant="outline" onClick={() => setEdit("new")}>Tambah {singular}</Button>}
    </div>
    {revision > 0 && <Notice success>Perubahan disimpan.</Notice>}
    {edit && <Editor<T> key={edit === "new" ? "new" : edit.id} title={(edit === "new" ? "Tambah " : "Ubah ") + singular}
      method={edit === "new" ? "POST" : "PATCH"} path={edit === "new" ? path : path + "/" + edit.id} fields={fields}
      initial={edit === "new" ? {} : initial(edit)} onCancel={() => setEdit(null)}
      onSaved={() => { setEdit(null); setRevision((value) => value + 1); }} />}
    <Records<T> key={revision} path={path} empty={"Belum ada " + singular + "."}
      render={(row) => <div className="flex items-start justify-between gap-3">
        <div className="min-w-0"><p className="font-semibold">{row.name}</p><p className="break-words text-sm text-subtle">{description(row)}</p></div>
        {editable && <Button variant="ghost" onClick={() => setEdit(row)} aria-label={"Ubah " + row.name}>Ubah</Button>}
      </div>} />
  </section>;
}

function BillingPanel({ path, editable }: { path: string; editable: boolean }) {
  const { data, error, refresh } = useRecords<{ data: Billing | null }>(path);
  const [editing, setEditing] = useState(false);
  const [saved, setSaved] = useState(false);
  const profile = data?.data;

  return <section className="panel space-y-4" aria-label="Profil penagihan">
    <div className="flex flex-wrap items-center justify-between gap-2">
      <h2 className="text-lg font-semibold">Profil penagihan</h2>
      {data && editable && <Button variant="outline" onClick={() => setEditing(true)}>{profile ? "Ubah profil penagihan" : "Tambah profil penagihan"}</Button>}
    </div>
    {saved && <Notice success>Profil penagihan disimpan.</Notice>}
    {error ? <DataError error={error} onRetry={refresh} /> : !data ? <Loading /> : <div className="space-y-3">
      {profile ? <div><p className="font-semibold">{profile.billing_name}</p><p className="text-sm text-subtle">{profile.address}</p>
        <p className="text-sm text-subtle">{profile.email ? profile.email + " · " : ""}Jatuh tempo {profile.payment_term_days} hari</p></div>
        : <p className="text-sm text-subtle">Profil penagihan belum tersedia.</p>}
      {editing && <Editor<Billing> title="Profil penagihan" path={path} method="PUT" fields={billingFields}
        initial={profile ? { ...profile, email: profile.email ?? "", payment_term_days: String(profile.payment_term_days) } : {}}
        onCancel={() => setEditing(false)} onSaved={() => { setEditing(false); setSaved(true); refresh(); }} />}
    </div>}
  </section>;
}
