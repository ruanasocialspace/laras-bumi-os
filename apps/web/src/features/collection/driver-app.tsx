"use client";

import { useCallback, useEffect, useRef, useState, useSyncExternalStore, type FormEvent } from "react";
import { Brand } from "@/components/brand";
import { Button } from "@/components/ui/button";
import { Loading, Notice } from "@/components/ui/feedback";
import { Records, dateLabel, timeLabel } from "@/features/management/shared";
import { api, ApiError, errorMessage, type Account, type Page } from "@/lib/api";
import {
  actorFromDisk, addQueued, clearActiveDriver, readQueue, rememberActor, saveTasks, tasksFromDisk,
  type Evidence, type OfflineActor, type Payload, type Queued,
} from "./offline-store";
import { synchronize } from "./sync";
import { jakartaToday, mapUrl, pickupStatus, type DriverStatus, type Event, type Task } from "./types";

function subscribeOnline(listener: () => void) {
  window.addEventListener("online", listener);
  window.addEventListener("offline", listener);
  return () => {
    window.removeEventListener("online", listener);
    window.removeEventListener("offline", listener);
  };
}

const dayMilliseconds = 24 * 60 * 60 * 1000;
type Session = { actor: OfflineActor | null; message: string };

export function DriverApp() {
  const [session, setSession] = useState<Session | null>(null);
  const [offlineReady, setOfflineReady] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const unauthorized = useCallback(() => {
    setSession({ actor: null, message: "Masuk kembali dengan akun pengemudi yang sama untuk mengirim data tersimpan." });
    void clearActiveDriver().catch(() => undefined);
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    async function identify() {
      try {
        const { data } = await api<{ data: Account }>("/auth/me", { signal: controller.signal });
        if (controller.signal.aborted) return;
        if (!data.permissions.includes("collections.drive")) {
          await clearActiveDriver();
          setSession({ actor: null, message: "Akun ini tidak memiliki izin pengemudi." });
          return;
        }
        const saved = await actorFromDisk();
        if (saved && saved.id !== data.id) await clearActiveDriver();
        const actor: OfflineActor = { key: "active", id: data.id, name: data.name, savedAt: Date.now() };
        await rememberActor(actor);
        if (!controller.signal.aborted) setSession({ actor, message: "" });
      } catch (error) {
        if (controller.signal.aborted) return;
        if (error instanceof ApiError && error.status === 401) { unauthorized(); return; }
        if (!navigator.onLine || error instanceof TypeError) {
          const cached = await actorFromDisk().catch(() => undefined);
          if (cached && Date.now() - cached.savedAt < dayMilliseconds) {
            setSession({ actor: cached, message: "" });
            return;
          }
        }
        setSession({ actor: null, message: "Hubungkan perangkat dan masuk untuk memuat tugas pengemudi." });
      }
    }
    void identify();
    return () => controller.abort();
  }, [attempt, unauthorized]);

  const actorId = session?.actor?.id;
  useEffect(() => {
    if (!actorId || !("serviceWorker" in navigator)) return;
    let active = true;
    const check = () => { if (active && navigator.serviceWorker.controller) setOfflineReady(true); };
    navigator.serviceWorker.addEventListener("controllerchange", check);
    async function prepare() {
      await navigator.serviceWorker.register("/driver/sw.js", { scope: "/driver/" });
      await navigator.serviceWorker.ready;
      void navigator.storage?.persist?.().catch(() => undefined);
      check();
    }
    void prepare().catch(() => undefined);
    return () => { active = false; navigator.serviceWorker.removeEventListener("controllerchange", check); };
  }, [actorId]);

  return <div className="min-h-screen">
    <header className="bg-primary px-5 py-4 text-white"><div className="mx-auto max-w-xl"><Brand /></div></header>
    <main className="mx-auto max-w-xl space-y-5 p-4 sm:p-6">
      {!session ? <Loading>Memeriksa sesi pengemudi…</Loading> : session.actor
        ? <DriverDesk key={session.actor.id} actor={session.actor} offlineReady={offlineReady} onSessionLost={unauthorized} />
        : <section className="panel space-y-4"><h1 className="text-xl font-bold">Aplikasi pengemudi</h1>
          <Notice>{session.message}</Notice>
          <div className="flex flex-wrap gap-3">
            <a className="inline-flex min-h-10 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-white" href="/login?next=/driver/app">Masuk</a>
            <Button variant="outline" onClick={() => { setSession(null); setAttempt((value) => value + 1); }}>Periksa lagi</Button>
          </div>
        </section>}
    </main>
  </div>;
}

function DriverDesk({ actor, offlineReady, onSessionLost }: { actor: OfflineActor; offlineReady: boolean; onSessionLost: () => void }) {
  const online = useSyncExternalStore(subscribeOnline, () => navigator.onLine, () => true);
  const [date, setDate] = useState(jakartaToday);
  const [tab, setTab] = useState<"tasks" | "history">("tasks");
  const [snapshot, setSnapshot] = useState<{ date: string; tasks: Task[] } | null>(null);
  const [queue, setQueue] = useState<Queued[]>([]);
  const [notice, setNotice] = useState("");
  const [loadingError, setLoadingError] = useState("");
  const [synced, setSynced] = useState(0);
  const [refresh, setRefresh] = useState(0);
  const [syncing, setSyncing] = useState(false);
  const [signout, setSignout] = useState(false);
  const inProgress = useRef(false);

  const syncNow = useCallback(async () => {
    if (!navigator.onLine || inProgress.current) return;
    inProgress.current = true;
    try {
      const pending = await readQueue(actor.id);
      if (pending.length > 0) {
        setSyncing(true);
        setNotice("");
      }
      const count = await synchronize(actor.id);
      setSynced((value) => value + count);
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        onSessionLost();
        return;
      }
      setNotice("Sinkronisasi dijeda. " + errorMessage(error));
    } finally {
      inProgress.current = false;
      setSyncing(false);
      setRefresh((value) => value + 1);
    }
  }, [actor.id, onSessionLost]);

  useEffect(() => {
    if (!online) return;
    let active = true;
    queueMicrotask(() => { if (active) void syncNow(); });
    window.addEventListener("focus", syncNow);
    return () => {
      active = false;
      window.removeEventListener("focus", syncNow);
    };
  }, [online, syncNow]);

  useEffect(() => {
    let active = true;
    async function load() {
      try {
        const cached = date ? await tasksFromDisk(actor.id, date) : [];
        if (!active) return;
        setQueue(await readQueue(actor.id));
        if (!active) return;
        if (cached) setSnapshot({ date, tasks: cached });
        if (!online || !date) {
          if (!cached) setSnapshot({ date, tasks: [] });
          setLoadingError(cached ? "" : "Tanggal ini belum diunduh saat online.");
          return;
        }
        const tasks: Task[] = [];
        let page = 1;
        let last = 1;
        do {
          const result = await api<Page<Task>>("/pickup-tasks?assigned=1&service_date=" + encodeURIComponent(date) + "&per_page=100&page=" + page,
            { headers: { "X-Driver-Actor": actor.id } });
          tasks.push(...result.data);
          last = result.meta.last_page;
        } while (active && ++page <= last);
        if (!active) return;
        await saveTasks({ actorId: actor.id, date, tasks });
        if (!active) return;
        setSnapshot({ date, tasks });
        setLoadingError("");
      } catch (error) {
        if (!active) return;
        if (error instanceof ApiError && [401, 403].includes(error.status)) onSessionLost();
        else setLoadingError(errorMessage(error));
      }
    }
    void load();
    return () => { active = false; };
  }, [actor.id, date, online, refresh, onSessionLost]);

  async function capture(task: Task, payload: Omit<Payload, "client_event_id" | "captured_at" | "expected_revision">, files: Evidence[]) {
    const id = crypto.randomUUID();
    const expectedRevision = task.revision;
    await addQueued({
      id, actorId: actor.id, taskId: task.id, date: task.service_date, expectedRevision, files,
      payload: { ...payload, client_event_id: id, expected_revision: expectedRevision, captured_at: new Date().toISOString() },
    });
    setQueue(await readQueue(actor.id));
    void syncNow();
  }

  async function logout() {
    if (!online || inProgress.current) return;
    try {
      const pending = await readQueue(actor.id);
      if (pending.length && !signout) { setSignout(true); return; }
      setNotice("");
      await api("/auth/logout", { method: "POST" });
      await clearActiveDriver();
      window.location.assign("/login");
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) onSessionLost();
      else setNotice(errorMessage(error));
    }
  }

  const problems = queue.filter((event) => event.problem);
  const tasks = snapshot?.date === date ? snapshot.tasks : undefined;
  return <div className="space-y-5">
    <section className="panel space-y-3" aria-label="Status sinkronisasi">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div><h1 className="text-2xl font-bold">Tugas pengemudi</h1><p className="text-sm text-subtle">{actor.name}</p></div>
        <span role="status" className="rounded-full bg-muted px-3 py-1 text-sm font-medium">{online ? "Online" : "Offline"}</span>
      </div>
      <p className="text-sm text-subtle">{offlineReady ? "Aplikasi siap dibuka offline setelah tugas diunduh." : "Menyiapkan akses offline. Tetap terhubung hingga siap."}</p>
      <p className="text-sm font-medium" aria-live="polite">Antrean: {queue.length} · Perlu perhatian: {problems.length} · Terkirim sesi ini: {synced}</p>
      {notice && <Notice>{notice}</Notice>}
      {problems.map((event) => <Notice key={event.id}>Kejadian {pickupStatus[event.payload.type]} ({event.taskId.slice(0, 8)}): {event.problem} Bukti tetap tersimpan.</Notice>)}
      <div className="flex flex-wrap gap-2">
        <Button variant="outline" onClick={() => void syncNow()} disabled={!online || syncing}>{syncing ? "Menyinkronkan…" : "Sinkronkan"}</Button>
        <Button variant="ghost" onClick={() => void logout()} disabled={!online || syncing}>Keluar</Button>
      </div>
      {signout && <div className="space-y-2">
        <Notice>Ada data belum terkirim. Data tetap tersimpan dan hanya tersedia saat masuk kembali dengan akun yang sama.</Notice>
        <div className="flex gap-2"><Button onClick={() => void logout()}>Keluar sekarang</Button>
          <Button variant="outline" onClick={() => setSignout(false)}>Batal</Button></div>
      </div>}
    </section>


    <div className="flex gap-2">
      <Button variant={tab === "tasks" ? "default" : "outline"} onClick={() => setTab("tasks")}>Tugas harian</Button>
      <Button variant={tab === "history" ? "default" : "outline"} onClick={() => setTab("history")}>Riwayat</Button>
    </div>
    {tab === "history" ? <section className="panel space-y-3" aria-label="Riwayat pengemudi">
      <h2 className="font-semibold">Riwayat pengambilan</h2>
      {online ? <DriverHistoryList actorId={actor.id} /> : <p className="text-sm text-subtle">Riwayat lengkap tersedia saat online. Tugas yang telah diunduh tersedia di Tugas harian.</p>}
    </section> : <section className="space-y-3" aria-label="Tugas harian">
      <label className="block text-sm font-semibold">Tanggal tugas (WIB)
        <input className="control mt-1" type="date" value={date} onChange={(event) => setDate(event.target.value)} />
      </label>
      {loadingError && <div className="space-y-2"><Notice>{loadingError}</Notice>
        <Button variant="outline" onClick={() => setRefresh((value) => value + 1)}>Muat ulang tugas</Button></div>}
      {!tasks ? <Loading /> : tasks.length === 0 ? <p className="panel text-sm text-subtle">Belum ada tugas pada tanggal ini.</p>
        : tasks.map((task) => {
          const pending = queue.filter((entry) => entry.taskId === task.id && entry.expectedRevision >= task.revision);
          const latest = pending.at(-1);
          const state = latest ? { ...task, status: latest.payload.type, revision: latest.expectedRevision + 1 } : task;
          return <DriverTask key={task.id} task={state} pending={pending.length}
            blocked={queue.some((entry) => entry.taskId === task.id && !!entry.problem)}
            onCapture={(payload, files) => capture(state, payload, files)} />;
        })}
    </section>}
  </div>;
}

function DriverHistoryList({ actorId }: { actorId: string }) {
  return <Records<Task> path="/pickup-tasks?assigned=1&history=1" empty="Belum ada riwayat."
    headers={{ "X-Driver-Actor": actorId }} render={(task) => <DriverHistory task={task} />} />;
}

function DriverHistory({ task }: { task: Task }) {
  const [open, setOpen] = useState(false);
  return <div className="space-y-2">
    <p className="font-semibold">{task.customer_name} · {pickupStatus[task.status]}</p>
    <p className="text-sm text-subtle">{dateLabel(task.service_date)} · {task.location_name}</p>
    <Button variant="outline" onClick={() => setOpen(!open)}>{open ? "Tutup rincian" : "Lihat rincian"}</Button>
    {open && <Records<Event> path={"/pickup-tasks/" + task.id + "/events"} empty="Belum ada kejadian."
      render={(event) => <div className="space-y-1 text-sm">
        <p className="font-medium">{pickupStatus[event.type]} · {timeLabel(event.captured_at)}</p>
        {event.reason && <p>{event.reason}</p>}
        {event.weight_kg && <p>{event.weight_kg} kg · {event.weight_basis === "estimated" ? "Perkiraan" : "Diukur di lapangan"}</p>}
      </div>} />}
  </div>;
}

type Capture = (payload: Omit<Payload, "client_event_id" | "captured_at" | "expected_revision">, files: Evidence[]) => Promise<void>;

function DriverTask({ task, blocked, pending, onCapture }: { task: Task; blocked: boolean; pending: number; onCapture: Capture }) {
  const [action, setAction] = useState<"completion" | "failure" | null>(null);
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState("");
  const [reason, setReason] = useState("");
  const active = ["assigned", "en_route", "arrived"].includes(task.status) && !blocked;

  async function record(type: DriverStatus, note?: string) {
    setBusy(true);
    setFailure("");
    try {
      await onCapture({ type, ...(note ? { reason: note } : {}) }, []);
      setAction(null);
    } catch {
      setFailure("Data belum tersimpan. Muat ulang daftar jika tugas sudah diperbarui di tab lain, lalu coba lagi.");
    } finally {
      setBusy(false);
    }
  }

  return <article className="panel space-y-3" aria-label={"Tugas " + task.customer_name}>
    <div className="flex flex-wrap items-start justify-between gap-2">
      <div><h2 className="text-lg font-bold">{task.customer_name}</h2>
        <p className="text-sm text-subtle">{task.slot.slice(0, 5)} WIB · {task.location_name}</p></div>
      <span className="rounded-full bg-muted px-3 py-1 text-sm font-medium">{pickupStatus[task.status]}</span>
    </div>
    <p className="text-sm">{task.address}</p>
    {task.vehicle_registration && <p className="text-sm text-subtle">Kendaraan {task.vehicle_registration}</p>}
    <a className="inline-flex min-h-11 items-center font-semibold text-primary underline" href={mapUrl(task)} target="_blank" rel="noreferrer">Buka peta penjemputan</a>
    {pending > 0 && <p className="text-sm font-medium" role="status">{pending} perubahan tersimpan di perangkat.</p>}
    {failure && <Notice>{failure}</Notice>}
    {active && <div className="flex flex-wrap gap-2">
      {task.status === "assigned" && <Button className="min-h-12" disabled={busy} onClick={() => void record("en_route")}>Mulai perjalanan</Button>}
      {task.status === "en_route" && <Button className="min-h-12" disabled={busy} onClick={() => void record("arrived")}>Saya sudah tiba</Button>}
      {task.status === "arrived" && <Button className="min-h-12" disabled={busy} onClick={() => setAction("completion")}>Selesaikan pengambilan</Button>}
      <Button className="min-h-12" variant="outline" disabled={busy} onClick={() => setAction("failure")}>Laporkan gagal</Button>
    </div>}
    {active && action === "failure" && <form className="space-y-3" onSubmit={(event) => { event.preventDefault(); void record("failed", reason.trim()); }}>
      <label className="block text-sm font-semibold">Alasan gagal<textarea className="control mt-1" required maxLength={500}
        value={reason} onChange={(event) => setReason(event.target.value)} /></label>
      <div className="flex gap-2"><Button type="submit" disabled={busy || !reason.trim()}>Simpan kegagalan</Button>
        <Button variant="outline" onClick={() => setAction(null)}>Batal</Button></div>
    </form>}
    {active && task.status === "arrived" && action === "completion" &&
      <Completion onCapture={onCapture} close={() => setAction(null)} />}
  </article>;
}

function Completion({ onCapture, close }: { onCapture: Capture; close: () => void }) {
  const [position, setPosition] = useState<{ latitude: number; longitude: number; accuracy_m: number } | null>(null);
  const [photo, setPhoto] = useState<File | null>(null);
  const [weight, setWeight] = useState("");
  const [basis, setBasis] = useState<"estimated" | "field_measured">("estimated");
  const [pending, setPending] = useState(false);
  const [gpsBusy, setGpsBusy] = useState(false);
  const [error, setError] = useState("");

  function locate() {
    if (!navigator.geolocation) { setError("GPS tidak tersedia pada perangkat ini."); return; }
    setError("");
    setGpsBusy(true);
    navigator.geolocation.getCurrentPosition(({ coords }) => {
      setGpsBusy(false);
      if (coords.accuracy > 10000) { setError("Akurasi GPS terlalu rendah. Coba ambil lokasi lagi."); return; }
      setPosition({ latitude: coords.latitude, longitude: coords.longitude, accuracy_m: coords.accuracy });
    }, () => { setGpsBusy(false); setError("GPS gagal diambil. Periksa izin lokasi perangkat dan coba lagi."); },
    { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    const kg = weight.trim().replace(",", ".");
    if (!position || !photo) { setError("Ambil lokasi GPS dan pilih foto sebelum menyimpan."); return; }
    if (!/^[0-9]{1,9}(?:\.[0-9]{1,3})?$/.test(kg) || Number(kg) <= 0) {
      setError("Masukkan berat lebih dari 0 kg dengan maksimal tiga angka desimal.");
      return;
    }
    const fileId = crypto.randomUUID();
    setPending(true);
    try {
      await onCapture({ type: "completed", ...position, weight_kg: kg, weight_basis: basis, evidence_ids: [fileId] },
        [{ id: fileId, blob: photo, name: photo.name }]);
      close();
    } catch {
      setError("Bukti belum tersimpan. Pastikan ruang penyimpanan tersedia dan coba lagi.");
    } finally {
      setPending(false);
    }
  }

  return <form className="space-y-4 rounded-xl border border-line bg-background p-4" aria-label="Bukti penyelesaian" onSubmit={submit}>
    <h3 className="font-semibold">Bukti penyelesaian</h3>
    {error && <Notice>{error}</Notice>}
    <label className="block text-sm font-semibold">Foto bukti
      <input type="file" accept="image/jpeg,image/png" capture="environment" className="control mt-1"
        onChange={(event) => {
          const file = event.target.files?.[0];
          if (file && (file.size > 8 * 1024 * 1024 || !["image/jpeg", "image/png"].includes(file.type))) {
            setPhoto(null);
            setError("Gunakan foto JPEG atau PNG maksimal 8 MB.");
            return;
          }
          setError("");
          setPhoto(file ?? null);
        }} />
    </label>
    <div className="space-y-2"><Button variant="outline" onClick={locate} disabled={gpsBusy}>{gpsBusy ? "Mengambil GPS…" : "Ambil lokasi GPS"}</Button>
      {position && <p className="text-sm font-medium" role="status">GPS tersedia · akurasi {Math.round(position.accuracy_m)} m</p>}</div>
    <div className="grid gap-3 sm:grid-cols-2">
      <label className="text-sm font-semibold">Berat (kg)<input className="control mt-1" inputMode="decimal" value={weight}
        required onChange={(event) => setWeight(event.target.value)} /></label>
      <label className="text-sm font-semibold">Dasar berat<select className="control mt-1" value={basis}
        onChange={(event) => setBasis(event.target.value === "field_measured" ? "field_measured" : "estimated")}>
        <option value="estimated">Perkiraan</option><option value="field_measured">Diukur di lapangan</option></select></label>
    </div>
    <div className="flex flex-wrap gap-2"><Button className="min-h-12" disabled={pending} type="submit">Simpan bukti</Button>
      <Button variant="outline" onClick={close} disabled={pending}>Batal</Button></div>
  </form>;
}
