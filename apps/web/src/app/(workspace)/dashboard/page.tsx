"use client";

import { useEffect, useState } from "react";
import { CalendarClock, Coins, Scale } from "lucide-react";
import { useActor } from "@/features/session/workspace";
import { api, errorMessage, type Reference } from "@/lib/api";
import { Loading, Notice } from "@/components/ui/feedback";
import { Button } from "@/components/ui/button";

export default function DashboardPage() {
  const actor = useActor();
  const [reference, setReference] = useState<Reference | null>(null);
  const [failure, setFailure] = useState("");
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    api<{ data: Reference }>("/reference", { signal: controller.signal })
      .then(({ data }) => setReference(data))
      .catch((error: unknown) => { if (!controller.signal.aborted) setFailure(errorMessage(error)); });
    return () => controller.abort();
  }, [attempt]);

  const values = reference ? [
    { label: "Mata uang", value: reference.currency, icon: Coins },
    { label: "Satuan berat", value: reference.mass_unit, icon: Scale },
    { label: "Zona waktu", value: reference.timezone, icon: CalendarClock },
  ] : [];

  return (
    <div className="space-y-7">
      <section className="overflow-hidden rounded-2xl border border-primary bg-primary p-6 text-white sm:p-9">
        <div className="mb-5 h-1 w-10 rounded bg-gold" aria-hidden />
        <h1 className="text-3xl font-bold tracking-tight">Dasbor</h1>
        <p className="mt-2 text-white/85">Selamat datang, {actor.name}.</p>
      </section>
      <section aria-labelledby="ref-heading">
        <h2 id="ref-heading" className="mb-4 text-lg font-semibold">Pengaturan operasional</h2>
        {failure ? (
          <div className="space-y-3"><Notice>{failure}</Notice><Button variant="outline" onClick={() => { setFailure(""); setAttempt((value) => value + 1); }}>Coba lagi</Button></div>
        ) : !reference ? <Loading /> : (
          <dl className="grid gap-4 sm:grid-cols-3">
            {values.map(({ label, value, icon: Icon }) => (
              <div key={label} className="panel">
                <Icon aria-hidden className="mb-3 size-5 text-primary" />
                <dt className="text-sm text-subtle">{label}</dt>
                <dd className="mt-1 text-lg font-semibold">{value || "Belum ditetapkan"}</dd>
              </div>
            ))}
          </dl>
        )}
      </section>
    </div>
  );
}
