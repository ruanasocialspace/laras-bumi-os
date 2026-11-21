"use client";

import { useState, type ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { Notice } from "@/components/ui/feedback";
import { Editor, Records, contractStatus, dateLabel, timeLabel, type Field } from "@/features/management/shared";
import type { Customer, Location } from "@/features/management/types";
import { useActor } from "@/features/session/workspace";
import { api, errorMessage } from "@/lib/api";
import { jakartaToday, mapUrl, pickupStatus, type Event, type Task } from "./types";

type Driver = { id: string; name: string };
type Vehicle = { id: string; registration: string; name: string; active: boolean };
const vehicleFields: Field[] = [
  { name: "registration", label: "Nomor polisi", required: true, max: 24 },
  { name: "name", label: "Nama kendaraan", required: true, max: 120 },
  { name: "active", label: "Kendaraan aktif", type: "checkbox" },
];

export function DispatchBoard() {
  const actor = useActor();
  const canDispatch = actor.permissions.includes("collections.dispatch");
  const [date, setDate] = useState(jakartaToday);
  const [tab, setTab] = useState<"dispatch" | "vehicles">("dispatch");
  const [manual, setManual] = useState(false);
  const [revision, setRevision] = useState(0);
  const [status, setStatus] = useState<{ text: string; success: boolean } | null>(null);
  const [busy, setBusy] = useState(false);

  if (!actor.permissions.includes("collections.read")) return <Notice>Anda tidak memiliki izin untuk melihat pengambilan.</Notice>;

  async function generate() {
    setBusy(true);
    setStatus(null);
    try {
      const { data } = await api<{ data: { created: number } }>("/pickup-tasks/generate", {
        method: "POST", body: JSON.stringify({ service_date: date }),
      });
      setStatus({ text: String(data.created) + " pengambilan rutin dibuat.", success: true });
      setRevision((value) => value + 1);
    } catch (error) {
      setStatus({ text: errorMessage(error), success: false });
    } finally {
      setBusy(false);
    }
  }

  return <div className="space-y-5">
    <h1 className="text-3xl font-bold tracking-tight">Pengambilan</h1>
    <nav aria-label="Bagian pengambilan" className="flex flex-wrap gap-2">
      <Button variant={tab === "dispatch" ? "default" : "outline"} aria-pressed={tab === "dispatch"} onClick={() => setTab("dispatch")}>Penugasan</Button>
      <Button variant={tab === "vehicles" ? "default" : "outline"} aria-pressed={tab === "vehicles"} onClick={() => setTab("vehicles")}>Kendaraan</Button>
    </nav>
    {tab === "vehicles" ? <Vehicles editable={canDispatch} /> :
      <section className="panel space-y-4" aria-label="Papan penugasan">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <label className="text-sm font-semibold">Tanggal layanan (WIB)
            <input className="control mt-1" type="date" value={date} onChange={(event) => { setDate(event.target.value); setStatus(null); setManual(false); }} />
          </label>
          {canDispatch && <div className="flex flex-wrap gap-2">
            <Button onClick={() => setManual(!manual)} disabled={!date}>Tambah pengambilan</Button>
            <Button variant="outline" onClick={generate} disabled={busy || !date}>{busy ? "Memproses…" : "Buat jadwal rutin"}</Button>
          </div>}
        </div>
        {status && <Notice success={status.success}>{status.text}</Notice>}
        {manual && <ManualTask key={date} date={date} onClose={() => setManual(false)} onSaved={(task) => {
          setDate(task.service_date);
          setManual(false);
          setRevision((value) => value + 1);
          setStatus({ text: "Pengambilan tersimpan.", success: true });
        }} />}
        {date ? <Records<Task> key={date + revision} path={"/pickup-tasks?service_date=" + encodeURIComponent(date)}
          empty="Belum ada pengambilan pada tanggal ini."
          render={(task) => <TaskPanel task={task} editable={canDispatch}
            onSaved={(text) => { setStatus({ text, success: true }); setRevision((value) => value + 1); }} />} />
          : <p className="text-sm text-subtle">Pilih tanggal layanan.</p>}
      </section>}
  </div>;
}

function ManualTask({ date, onClose, onSaved }: { date: string; onClose: () => void; onSaved: (task: Task) => void }) {
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [location, setLocation] = useState<Location | null>(null);
  const [id] = useState(() => crypto.randomUUID());

  return <div className="space-y-4 rounded-xl border border-line p-4">
    <h2 className="font-semibold">Pengambilan baru</h2>
    <Choice<Customer> label="Pelanggan aktif" path="/customers?status=active" value={customer} select={(row) => { setCustomer(row); setLocation(null); }} />
    {customer && <Choice<Location> key={customer.id} label="Lokasi penjemputan" path={"/customers/" + customer.id + "/locations"}
      value={location} select={setLocation} description={(row) => row.address} />}
    {customer && location ? <Editor<Task> title="Waktu pengambilan" path="/pickup-tasks" fields={[
      { name: "service_date", label: "Tanggal pengambilan", type: "date", required: true },
      { name: "slot", label: "Waktu pengambilan (WIB)", type: "time", required: true },
    ]} initial={{ service_date: date }} normalize={(values) => ({ ...values, client_task_id: id, customer_id: customer.id, location_id: location.id })}
    onSaved={onSaved} onCancel={onClose} /> : <Button variant="outline" onClick={onClose}>Batal</Button>}
  </div>;
}

function Choice<T extends { id: string; name: string }>({ label, path, value, select, description }: {
  label: string; path: string; value: T | null; select: (item: T) => void; description?: (item: T) => ReactNode;
}) {
  const [open, setOpen] = useState(!value);
  return <section className="space-y-2" aria-label={label}>
    <div className="flex flex-wrap items-center justify-between gap-2">
      <p className="font-medium">{label}{value ? " · " + value.name : ""}</p>
      {value && <Button variant="ghost" onClick={() => setOpen(!open)}>{open ? "Tutup pilihan" : "Ganti pilihan"}</Button>}
    </div>
    {open && <Records<T> path={path} empty="Tidak ada pilihan yang tersedia." render={(row) => (
      <div className="flex items-start justify-between gap-2"><div><p className="font-medium">{row.name}</p>
        {description && <div className="text-sm text-subtle">{description(row)}</div>}</div>
        <Button variant="outline" onClick={() => { select(row); setOpen(false); }} aria-label={"Pilih " + row.name}>Pilih</Button>
      </div>
    )} />}
  </section>;
}

function TaskPanel({ task, editable, onSaved }: { task: Task; editable: boolean; onSaved: (message: string) => void }) {
  const [action, setAction] = useState<"assign" | "cancel" | "history" | null>(null);
  const active = ["scheduled", "assigned", "en_route", "arrived"].includes(task.status);
  const path = "/pickup-tasks/" + task.id;

  return <div className="space-y-3">
    <div className="flex flex-wrap items-start justify-between gap-2">
      <div><p className="font-semibold">{task.slot.slice(0, 5)} WIB · {task.customer_name}</p>
        <p className="text-sm text-subtle">{task.location_name} · {task.address}</p>
        <p className="text-sm text-subtle">{task.schedule_id ? "Jadwal rutin" : "Manual"}
          {task.driver_name && " · " + task.driver_name + " · " + task.vehicle_registration}</p></div>
      <span className="rounded-full bg-muted px-3 py-1 text-sm font-medium">{pickupStatus[task.status]}</span>
    </div>
    {active && task.contract_status && task.contract_status !== "active" &&
      <Notice>Periksa penugasan ini. Kontrak berstatus {contractStatus[task.contract_status].toLowerCase()}.</Notice>}
    <div className="flex flex-wrap gap-2">
      {editable && ["scheduled", "assigned"].includes(task.status) &&
        <Button variant="outline" onClick={() => setAction(action === "assign" ? null : "assign")}>Tugaskan</Button>}
      {editable && active && <Button variant="ghost" onClick={() => setAction(action === "cancel" ? null : "cancel")}>Batalkan</Button>}
      <Button variant="ghost" onClick={() => setAction(action === "history" ? null : "history")}>Riwayat</Button>
      <a className="inline-flex min-h-10 items-center px-3 text-sm font-semibold text-primary underline" href={mapUrl(task)} target="_blank" rel="noreferrer">Buka peta</a>
    </div>
    {action === "assign" && <Assignment task={task} onSaved={() => onSaved("Penugasan tersimpan.")} onClose={() => setAction(null)} />}
    {action === "cancel" && <Editor<Task> title="Batalkan pengambilan" path={path + "/cancel"}
      fields={[{ name: "reason", label: "Alasan pembatalan", required: true, type: "textarea", max: 500 }]}
      normalize={(values) => ({ ...values, expected_revision: task.revision })} onCancel={() => setAction(null)}
      onSaved={() => onSaved("Pengambilan dibatalkan.")} />}
    {action === "history" && <Records<Event> path={path + "/events"} empty="Belum ada riwayat." render={(event) => <EventRow event={event} taskId={task.id} />} />}
  </div>;
}

function Assignment({ task, onSaved, onClose }: { task: Task; onSaved: () => void; onClose: () => void }) {
  const [driver, setDriver] = useState<Driver | null>(null);
  const [vehicle, setVehicle] = useState<Vehicle | null>(null);
  return <div className="space-y-4 rounded-xl border border-line p-4">
    <Choice<Driver> label="Pengemudi" path="/collection-drivers" value={driver} select={setDriver} />
    <Choice<Vehicle> label="Kendaraan aktif" path="/vehicles?active=1" value={vehicle} select={setVehicle} description={(row) => row.registration} />
    {driver && vehicle ? <Editor<Task> title="Simpan penugasan" path={"/pickup-tasks/" + task.id + "/assignments"}
      fields={[]} normalize={() => ({ driver_id: driver.id, vehicle_id: vehicle.id, expected_revision: task.revision })}
      onSaved={onSaved} onCancel={onClose} /> : <Button variant="outline" onClick={onClose}>Batal</Button>}
  </div>;
}

function Vehicles({ editable }: { editable: boolean }) {
  const [editor, setEditor] = useState<Vehicle | "new" | null>(null);
  const [revision, setRevision] = useState(0);
  return <section className="panel space-y-4" aria-label="Daftar kendaraan">
    <div className="flex flex-wrap items-center justify-between gap-2">
      <h2 className="text-lg font-semibold">Kendaraan</h2>
      {editable && <Button onClick={() => setEditor("new")}>Tambah kendaraan</Button>}
    </div>
    {revision > 0 && <Notice success>Kendaraan tersimpan.</Notice>}
    {editor && <Editor<Vehicle> key={editor === "new" ? editor : editor.id} title={editor === "new" ? "Kendaraan baru" : "Ubah kendaraan"}
      path={editor === "new" ? "/vehicles" : "/vehicles/" + editor.id} method={editor === "new" ? "POST" : "PATCH"}
      fields={vehicleFields} initial={editor === "new" ? { active: true } : editor} onCancel={() => setEditor(null)}
      onSaved={() => { setEditor(null); setRevision((value) => value + 1); }} />}
    <Records<Vehicle> key={revision} path="/vehicles" empty="Belum ada kendaraan."
      render={(vehicle) => <div className="flex items-start justify-between gap-3">
        <div><p className="font-semibold">{vehicle.name} · {vehicle.registration}</p><p className="text-sm text-subtle">{vehicle.active ? "Aktif" : "Nonaktif"}</p></div>
        {editable && <Button variant="ghost" onClick={() => setEditor(vehicle)} aria-label={"Ubah " + vehicle.name}>Ubah</Button>}
      </div>} />
  </section>;
}

export function EventRow({ event, taskId }: { event: Event; taskId: string }) {
  return <div className="space-y-1">
    <p className="font-medium">{pickupStatus[event.type]}</p>
    <p className="text-sm text-subtle">{timeLabel(event.captured_at)}{event.reason ? " · " + event.reason : ""}</p>
    {event.weight_kg && <p className="text-sm">{event.weight_kg} kg · {event.weight_basis === "estimated" ? "Perkiraan" : "Diukur di lapangan"}</p>}
    {event.evidence_ids.map((id, index) => <a className="mr-4 text-sm font-semibold text-primary underline" key={id}
      href={"/api/v1/pickup-tasks/" + taskId + "/evidence/" + id + "/download"} target="_blank" rel="noreferrer">Foto {index + 1}</a>)}
  </div>;
}

export function CustomerPickupHistory({ customerId }: { customerId: string }) {
  return <section className="panel space-y-4" aria-label="Riwayat pengambilan">
    <h2 className="text-lg font-semibold">Riwayat pengambilan</h2>
    <Records<Task> path={"/customers/" + customerId + "/pickup-history"} empty="Belum ada riwayat pengambilan."
      render={(task) => <div><p className="font-semibold">{task.location_name} · {pickupStatus[task.status]}</p>
        <p className="text-sm text-subtle">{dateLabel(task.service_date)} · {task.slot.slice(0, 5)} WIB</p></div>} />
  </section>;
}
