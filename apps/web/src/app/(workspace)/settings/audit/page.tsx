"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Loading, Notice } from "@/components/ui/feedback";
import { Pagination } from "@/components/ui/pagination";
import { useActor } from "@/features/session/workspace";
import { api, errorMessage, type Page } from "@/lib/api";

type Entry = {
  id: string;
  action: string;
  actor_id: string | null;
  resource_type: string;
  resource_id: string | null;
  request_id: string;
  occurred_at: string;
  details: Record<string, unknown>;
};

const timestamp = new Intl.DateTimeFormat("id-ID", { timeZone: "Asia/Jakarta", dateStyle: "medium", timeStyle: "short" });
const actions: Record<string, string> = {
  "identity.login": "Masuk",
  "identity.logout": "Keluar",
  "identity.user_created": "Pengguna ditambahkan",
  "identity.user_updated": "Pengguna diubah",
  "identity.demo_user_created": "Akun lokal dibuat",
  "identity.admin_bootstrapped": "Administrator utama dibuat",
  "audit.viewed": "Log audit diakses",
};
const resources: Record<string, string> = { user: "Pengguna", audit_log: "Log audit" };

export default function AuditPage() {
  const actor = useActor();
  return actor.permissions.includes("audit.read") ? <AuditPanel /> : <Notice>Anda tidak memiliki izin untuk melihat halaman ini.</Notice>;
}

function AuditPanel() {
  const [page, setPage] = useState(1);
  const [results, setResults] = useState<Page<Entry> | null>(null);
  const [failure, setFailure] = useState("");
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    api<Page<Entry>>("/audit-logs?page=" + page, { signal: controller.signal })
      .then((entries) => { if (!controller.signal.aborted) setResults(entries); })
      .catch((error: unknown) => { if (!controller.signal.aborted) setFailure(errorMessage(error)); });
    return () => controller.abort();
  }, [page, attempt]);

  return (
    <div className="space-y-5">
      <div><p className="mb-1 text-sm text-subtle">Pengaturan</p><h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Log audit</h1></div>
      <section className="panel space-y-4">
        {failure ? <div className="space-y-3"><Notice>{failure}</Notice><Button variant="outline" onClick={() => { setFailure(""); setAttempt((value) => value + 1); }}>Coba lagi</Button></div>
          : !results ? <Loading />
          : results.data.length === 0 ? <p className="text-sm text-subtle">Belum ada riwayat audit.</p>
          : (
            <ul className="divide-y divide-line">
              {results.data.map((entry) => (
                <li key={entry.id} className="py-3">
                  <details>
                    <summary className="cursor-pointer text-sm font-medium">
                      {actions[entry.action] ?? entry.action}
                      <time dateTime={entry.occurred_at} className="ml-2 whitespace-nowrap text-xs font-normal text-subtle">{timestamp.format(new Date(entry.occurred_at))} WIB</time>
                    </summary>
                    <dl className="my-3 grid gap-2 break-all text-xs text-subtle sm:grid-cols-[max-content_1fr]">
                      <dt>Pelaku</dt><dd>{entry.actor_id ?? "Sistem"}</dd>
                      <dt>Sumber daya</dt><dd>{resources[entry.resource_type] ?? entry.resource_type} {entry.resource_id}</dd>
                      <dt>ID permintaan</dt><dd>{entry.request_id}</dd>
                    </dl>
                    {Object.keys(entry.details).length > 0 && <pre className="overflow-x-auto rounded-lg bg-muted p-3 text-xs">{JSON.stringify(entry.details, null, 2)}</pre>}
                  </details>
                </li>
              ))}
            </ul>
          )}
        {results && <Pagination page={page} last={results.meta.last_page} onChange={(next) => { setPage(next); setResults(null); setFailure(""); }} />}
      </section>
    </div>
  );
}
