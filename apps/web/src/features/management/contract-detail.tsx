"use client";

import { useState } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Loading, Notice } from "@/components/ui/feedback";
import { useActor } from "@/features/session/workspace";
import { editableContractFields } from "./customer-detail";
import { contractStatus, DataError, dateLabel, Editor, options, Records, rupiah, timeLabel, useRecords, type Field } from "./shared";
import type { Contract, ContractEvent, ContractStatus, Customer, Location, Rate, Schedule, Service } from "./types";

const linkStyle = "font-semibold text-primary hover:underline";
const pricing = { monthly: "Bulanan", per_pickup: "Per pengambilan", per_kg: "Per kilogram", one_time: "Satu kali" };
const weekdays = { "1": "Senin", "2": "Selasa", "3": "Rabu", "4": "Kamis", "5": "Jumat", "6": "Sabtu", "7": "Minggu" };
const transitionLabels: Partial<Record<ContractStatus, string>> = {
  active: "Aktifkan", suspended: "Tangguhkan", expired: "Catat kedaluwarsa", terminated: "Akhiri",
};
const transitions: Record<ContractStatus, ContractStatus[]> = {
  draft: ["active", "terminated"], active: ["suspended", "terminated"],
  suspended: ["active", "terminated"], expired: [], terminated: [],
};
const effectiveFields: Field[] = [
  { name: "effective_from", label: "Berlaku mulai", type: "date", required: true },
  { name: "effective_until", label: "Berlaku sebelum", type: "date" },
];

export function ContractDetail({ id }: { id: string }) {
  const actor = useActor();
  return actor.permissions.includes("contracts.read")
    ? <ContractProfile id={id} /> : <Notice>Anda tidak memiliki izin untuk melihat kontrak.</Notice>;
}

function ContractProfile({ id }: { id: string }) {
  const actor = useActor();
  const path = "/contracts/" + id;
  const { data, error, refresh } = useRecords<{ data: Contract }>(path);
  const [editing, setEditing] = useState(false);
  const [target, setTarget] = useState<ContractStatus | null>(null);
  const [serviceEdit, setServiceEdit] = useState<Service | "new" | null>(null);
  const [revision, setRevision] = useState(0);
  const [message, setMessage] = useState("");

  if (error) return <DataError error={error} onRetry={refresh} />;
  if (!data) return <Loading />;

  const contract = data.data;
  const draft = contract.status === "draft";
  const canWrite = actor.permissions.includes("contracts.write");
  const canSetPrices = actor.permissions.includes("contracts.pricing.write");
  const allowedTransitions = contract.status === contract.effective_status ? transitions[contract.status] : ["expired" as const];

  function changed(message: string) {
    setMessage(message);
    setRevision((value) => value + 1);
  }

  return <div className="space-y-5">
    <Link className={linkStyle} href="/contracts">Kembali ke kontrak</Link>
    <header className="panel space-y-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div><p className="text-sm text-subtle">{contract.code}</p>
          <h1 className="mt-1 text-3xl font-bold tracking-tight">{contract.name}</h1></div>
        <span className="rounded-full bg-muted px-3 py-1 text-sm font-semibold">{contractStatus[contract.effective_status]}</span>
      </div>
      <p className="text-sm text-subtle">{dateLabel(contract.starts_on)} · {dateLabel(contract.ends_on)}</p>
      {actor.permissions.includes("customers.read") && <CustomerLink id={contract.customer_id} />}
      <div className="flex flex-wrap gap-2">
        {draft && canWrite && <Button variant="outline" onClick={() => { setTarget(null); setEditing(true); }}>Ubah kontrak</Button>}
        {actor.permissions.includes("contracts.activate") && allowedTransitions.map((status) => (
          <Button key={status} variant={status === "active" ? "default" : "outline"}
            onClick={() => { setEditing(false); setTarget(status); }}>{transitionLabels[status]}</Button>
        ))}
      </div>
    </header>
    {message && <Notice success>{message}</Notice>}
    {editing && <Editor<Contract> title="Ubah kontrak" path={path} method="PATCH" fields={editableContractFields}
      initial={{ name: contract.name, starts_on: contract.starts_on, ends_on: contract.ends_on ?? "" }}
      onCancel={() => setEditing(false)} onSaved={() => { setEditing(false); changed("Kontrak disimpan."); refresh(); }} />}
    {target && <Editor<Contract> key={target} title={transitionLabels[target] + " kontrak"} path={path + "/transitions"}
      fields={[{ name: "reason", label: "Alasan", type: "textarea", max: 500, required: target === "suspended" || target === "terminated" }]}
      normalize={(values) => ({ ...values, status: target })} onCancel={() => setTarget(null)}
      onSaved={() => { setTarget(null); changed("Status kontrak disimpan."); refresh(); }} />}

    <section className="panel space-y-4" aria-label="Layanan kontrak">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">Layanan kontrak</h2>
        {draft && canWrite && <Button onClick={() => setServiceEdit("new")}>Tambah layanan</Button>}
      </div>
      {serviceEdit && <ServiceEditor key={serviceEdit === "new" ? "new" : serviceEdit.id} path={path} customerId={contract.customer_id}
        service={serviceEdit === "new" ? undefined : serviceEdit} onCancel={() => setServiceEdit(null)}
        onSaved={() => { setServiceEdit(null); changed("Layanan disimpan."); }} />}
      <Records<Service> key={revision} path={path + "/services"} empty="Belum ada layanan."
        render={(service) => <ServicePanel contract={contract} service={service}
          edit={draft && canWrite ? () => setServiceEdit(service) : undefined}
          canSetPrices={canSetPrices} canWrite={canWrite} />} />
    </section>

    <section className="panel space-y-4" aria-label="Riwayat status kontrak">
      <h2 className="text-lg font-semibold">Riwayat status kontrak</h2>
      <Records<ContractEvent> key={revision} path={path + "/history"} empty="Belum ada riwayat."
        render={(event) => <div>
          <p className="font-semibold">{event.from_status ? contractStatus[event.from_status] + " → " : ""}{contractStatus[event.to_status]}</p>
          <time dateTime={event.occurred_at} className="text-sm text-subtle">{timeLabel(event.occurred_at)}</time>
          {event.reason && <p className="mt-1 text-sm">{event.reason}</p>}
        </div>} />
    </section>
  </div>;
}

function CustomerLink({ id }: { id: string }) {
  const { data, error, refresh } = useRecords<{ data: Customer }>("/customers/" + id);
  if (error) return <DataError error={error} onRetry={refresh} />;
  return data ? <Link className={linkStyle + " text-sm"} href={"/customers/" + id}>{data.data.name}</Link> : <Loading>Memuat pelanggan…</Loading>;
}

function ServiceEditor({ path, customerId, service, onCancel, onSaved }: {
  path: string; customerId: string; service?: Service; onCancel: () => void; onSaved: () => void;
}) {
  const [selected, setSelected] = useState(service ? { id: service.location_id, name: service.location_name } : null);
  return <div className="space-y-4 rounded-xl border border-line p-4">
    <fieldset className="space-y-2">
      <legend className="text-sm font-semibold">Lokasi layanan</legend>
      {selected && <p className="text-sm text-primary">Lokasi dipilih: {selected.name}</p>}
      <Records<Location> path={"/customers/" + customerId + "/locations"} empty="Tambahkan lokasi di profil pelanggan terlebih dahulu."
        render={(location) => <div className="flex items-start justify-between gap-3">
          <div><p className="font-semibold">{location.name}</p><p className="text-sm text-subtle">{location.address}</p></div>
          <Button variant="outline" disabled={selected?.id === location.id} aria-label={"Pilih " + location.name}
            onClick={() => setSelected({ id: location.id, name: location.name })}>{selected?.id === location.id ? "Dipilih" : "Pilih"}</Button>
        </div>} />
    </fieldset>
    <Editor<Service> title={service ? "Ubah layanan" : "Layanan baru"} path={path + "/services" + (service ? "/" + service.id : "")}
      method={service ? "PATCH" : "POST"} fields={[{ name: "name", label: "Nama layanan", max: 160, required: true }]}
      initial={service ? { name: service.name } : {}} normalize={(values) => ({ ...values, location_id: selected?.id ?? null })}
      onSaved={onSaved} onCancel={onCancel} />
  </div>;
}

function ServicePanel({ contract, service, edit, canSetPrices, canWrite }: {
  contract: Contract; service: Service; edit?: () => void; canSetPrices: boolean; canWrite: boolean;
}) {
  const [open, setOpen] = useState(false);
  const [rate, setRate] = useState<Rate | "new" | null>(null);
  const [schedule, setSchedule] = useState<Schedule | "new" | null>(null);
  const [rateVersion, setRateVersion] = useState(0);
  const [scheduleVersion, setScheduleVersion] = useState(0);
  const [message, setMessage] = useState("");
  const draft = contract.status === "draft";
  const amendable = draft || ["active", "suspended"].includes(contract.effective_status);
  const path = "/contracts/" + contract.id;
  const rates = path + "/services/" + service.id + "/rates";
  const schedules = path + "/pickup-schedules";

  return <div className="space-y-3">
    <div className="flex flex-wrap items-start justify-between gap-2">
      <div><p className="font-semibold">{service.name}</p><p className="text-sm text-subtle">{service.location_name}</p></div>
      <div className="flex flex-wrap gap-2">
        {edit && <Button variant="ghost" onClick={edit} aria-label={"Ubah " + service.name}>Ubah</Button>}
        <Button variant="outline" aria-expanded={open} onClick={() => setOpen(!open)}>{open ? "Tutup tarif dan jadwal" : "Lihat tarif dan jadwal"}</Button>
      </div>
    </div>
    {open && <div className="grid gap-4 rounded-lg bg-background p-4 xl:grid-cols-2">
      {message && <div className="xl:col-span-2"><Notice success>{message}</Notice></div>}
      <section className="space-y-3" aria-label="Tarif layanan">
        <div className="flex flex-wrap items-center justify-between"><h3 className="font-semibold">Tarif</h3>
          {amendable && canSetPrices && <Button variant="outline" onClick={() => setRate("new")}>Tambah tarif</Button>}</div>
        {rate && <Editor<Rate> key={rate === "new" ? rate : rate.id} title={rate === "new" ? "Tarif baru" : "Ubah tarif"}
          path={rate === "new" ? rates : rates + "/" + rate.id} method={rate === "new" ? "POST" : "PATCH"} fields={[
            { name: "pricing_model", label: "Model tarif", options: options(pricing), required: true },
            { name: "unit_rate", label: "Tarif (IDR)", inputMode: "decimal", required: true, max: 21 },
            ...effectiveFields,
            ...(rate === "new" ? [{ name: "replace_open", label: "Akhiri tarif sebelumnya saat tarif baru mulai", type: "checkbox" as const }] : []),
          ]} initial={rate === "new" ? { effective_from: draft ? contract.starts_on : "" } : { ...rate, effective_until: rate.effective_until ?? "" }}
          normalize={(values) => ({ ...values, unit_rate: String(values.unit_rate).trim().replace(",", ".") })}
          onCancel={() => setRate(null)} onSaved={() => { setRate(null); setMessage("Tarif disimpan."); setRateVersion((value) => value + 1); }} />}
        <Records<Rate> key={rateVersion} path={rates} empty="Belum ada tarif." render={(entry) => (
          <div className="flex items-start justify-between gap-2"><div>
            <p className="font-semibold">{rupiah(entry.unit_rate)} · {pricing[entry.pricing_model as keyof typeof pricing]}</p>
            <p className="text-sm text-subtle">{dateLabel(entry.effective_from)} · {entry.effective_until ? "sebelum " + dateLabel(entry.effective_until) : "tanpa tanggal akhir"}</p>
          </div>{draft && canSetPrices && <Button variant="ghost" onClick={() => setRate(entry)}>Ubah tarif</Button>}</div>
        )} />
      </section>
      <section className="space-y-3" aria-label="Jadwal layanan">
        <div className="flex flex-wrap items-center justify-between"><h3 className="font-semibold">Jadwal mingguan</h3>
          {amendable && canWrite && <Button variant="outline" onClick={() => setSchedule("new")}>Tambah jadwal</Button>}</div>
        {schedule && <Editor<Schedule> key={schedule === "new" ? schedule : schedule.id} title={schedule === "new" ? "Jadwal baru" : "Ubah jadwal"}
          path={schedule === "new" ? schedules : schedules + "/" + schedule.id} method={schedule === "new" ? "POST" : "PATCH"} fields={[
            { name: "weekday", label: "Hari", options: options(weekdays), required: true },
            { name: "slot", label: "Waktu penjemputan (WIB)", type: "time", required: true },
            ...effectiveFields,
          ]} initial={schedule === "new" ? { effective_from: draft ? contract.starts_on : "" } : {
            weekday: String(schedule.weekday), slot: schedule.slot.slice(0, 5), effective_from: schedule.effective_from, effective_until: schedule.effective_until ?? "",
          }} normalize={(values) => ({ ...values, service_id: service.id })} onCancel={() => setSchedule(null)}
          onSaved={() => { setSchedule(null); setMessage("Jadwal disimpan."); setScheduleVersion((value) => value + 1); }} />}
        <Records<Schedule> key={scheduleVersion} path={schedules + "?service_id=" + service.id} empty="Belum ada jadwal." render={(entry) => (
          <div className="flex items-start justify-between gap-2"><div>
            <p className="font-semibold">{weekdays[String(entry.weekday) as keyof typeof weekdays]}, {entry.slot.slice(0, 5)} WIB</p>
            <p className="text-sm text-subtle">{dateLabel(entry.effective_from)} · {entry.effective_until ? "sebelum " + dateLabel(entry.effective_until) : "tanpa tanggal akhir"}</p>
          </div>{draft && canWrite && <Button variant="ghost" onClick={() => setSchedule(entry)}>Ubah jadwal</Button>}</div>
        )} />
      </section>
    </div>}
  </div>;
}
