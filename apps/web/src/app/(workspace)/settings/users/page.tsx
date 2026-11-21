"use client";

import { useEffect, useState, type FormEvent } from "react";
import { Plus, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Loading, Notice } from "@/components/ui/feedback";
import { Pagination } from "@/components/ui/pagination";
import { AccountForm } from "@/features/identity/account-form";
import { useActor } from "@/features/session/workspace";
import { api, errorMessage, type Account, type Page, type RoleDetails } from "@/lib/api";

export default function UsersPage() {
  const actor = useActor();
  return actor.permissions.includes("identity.manage") ? <AccountsPanel /> : <Notice>Anda tidak memiliki izin untuk melihat halaman ini.</Notice>;
}

function AccountsPanel() {
  const [query, setQuery] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState<Page<Account> | null>(null);
  const [roles, setRoles] = useState<RoleDetails[]>([]);
  const [editor, setEditor] = useState<Account | "new" | null>(null);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    const query = new URLSearchParams({ page: String(page), q: search });
    Promise.all([
      api<Page<Account>>("/users?" + query, { signal: controller.signal }),
      api<{ data: RoleDetails[] }>("/roles", { signal: controller.signal }),
    ]).then(([accounts, roleList]) => {
      if (controller.signal.aborted) return;
      setData(accounts);
      setRoles(roleList.data);
    }).catch((failure: unknown) => {
      if (!controller.signal.aborted) setError(errorMessage(failure));
    });
    return () => controller.abort();
  }, [page, search, attempt]);

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setData(null);
    setError("");
    setPage(1);
    setSearch(query.trim());
    setAttempt((value) => value + 1);
  }

  function refresh() {
    setData(null);
    setError("");
    setAttempt((value) => value + 1);
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div><p className="mb-1 text-sm text-subtle">Pengaturan</p><h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Pengguna dan peran</h1></div>
        <Button onClick={() => { setEditor("new"); setSuccess(""); }} disabled={roles.length === 0}><Plus aria-hidden className="size-4" />Tambah pengguna</Button>
      </div>
      {success && <Notice success>{success}</Notice>}
      {editor && <AccountForm key={editor === "new" ? editor : editor.id} account={editor === "new" ? null : editor} roles={roles}
        onCancel={() => setEditor(null)}
        onSave={() => {
          setSuccess(editor === "new" ? "Pengguna ditambahkan." : "Perubahan pengguna disimpan.");
          setEditor(null);
          refresh();
        }} />}
      <section className="panel space-y-5" aria-label="Daftar pengguna">
        <form className="flex max-w-lg flex-wrap gap-2 sm:flex-nowrap" onSubmit={submitSearch}>
          <label htmlFor="user-search" className="sr-only">Cari berdasarkan nama atau email</label>
          <input id="user-search" className="control" type="search" maxLength={120} placeholder="Cari nama atau email" value={query} onChange={(event) => setQuery(event.target.value)} />
          <Button type="submit" variant="outline"><Search aria-hidden className="size-4" />Cari</Button>
        </form>
        {error ? <div className="space-y-3"><Notice>{error}</Notice><Button variant="outline" onClick={refresh}>Coba lagi</Button></div>
          : !data ? <Loading />
          : data.data.length === 0 ? <p className="text-sm text-subtle">Tidak ada pengguna yang cocok.</p>
          : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[560px] text-left text-sm">
                <thead className="border-b border-line text-subtle">
                  <tr><th scope="col" className="py-3 pr-4">Pengguna</th><th scope="col" className="pr-4">Peran</th><th scope="col" className="pr-4">Status</th><th scope="col" className="text-right">Tindakan</th></tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {data.data.map((account) => (
                    <tr key={account.id}>
                      <td className="py-3 pr-4"><div className="font-semibold">{account.name}</div><div className="text-subtle">{account.email}</div></td>
                      <td className="pr-4">{account.roles.map((role) => role.name).join(", ")}</td>
                      <td className="pr-4"><span className="whitespace-nowrap rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-ink">{account.active ? "Aktif" : "Nonaktif"}</span></td>
                      <td className="text-right"><Button variant="ghost" onClick={() => { setEditor(account); setSuccess(""); }} aria-label={"Ubah " + account.name}>Ubah</Button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        {data && <Pagination page={page} last={data.meta.last_page} onChange={(next) => { setPage(next); setData(null); setError(""); }} />}
      </section>
      {roles.length > 0 && (
        <section className="panel" aria-labelledby="role-catalog-title">
          <h2 id="role-catalog-title" className="mb-3 text-lg font-semibold">Katalog peran</h2>
          <ul className="grid gap-2 sm:grid-cols-2">
            {roles.map((role) => (
              <li key={role.code}>
                <details className="rounded-lg border border-line px-3 py-2 text-sm">
                  <summary className="cursor-pointer font-medium">{role.name}</summary>
                  <ul className="mt-3 space-y-1 break-all text-xs text-subtle">{role.permissions.map((permission) => <li key={permission}>{permission}</li>)}</ul>
                </details>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
