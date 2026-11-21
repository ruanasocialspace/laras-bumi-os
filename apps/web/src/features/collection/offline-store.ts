import type { DriverStatus, Status, Task } from "./types";

export type OfflineActor = { key: "active"; id: string; name: string; savedAt: number };
export type Evidence = { id: string; blob: Blob; name: string };
export type Payload = {
  client_event_id: string;
  type: DriverStatus;
  captured_at: string;
  expected_revision: number;
  reason?: string;
  latitude?: number;
  longitude?: number;
  accuracy_m?: number;
  weight_kg?: string;
  weight_basis?: "estimated" | "field_measured";
  evidence_ids?: string[];
};
export type Queued = {
  id: string; actorId: string; taskId: string; date: string;
  expectedRevision: number; payload: Payload; files: Evidence[];
  problem?: string;
};
type Snapshot = { actorId: string; date: string; tasks: Task[] };

let opening: Promise<IDBDatabase> | undefined;

function database() {
  if (opening) return opening;
  opening = new Promise<IDBDatabase>((resolve, reject) => {
    const request = indexedDB.open("laras-driver", 1);
    request.onupgradeneeded = () => {
      const db = request.result;
      db.createObjectStore("session", { keyPath: "key" });
      db.createObjectStore("tasks", { keyPath: ["actorId", "date"] }).createIndex("byActor", "actorId");
      const queue = db.createObjectStore("queue", { keyPath: "id" });
      queue.createIndex("byActor", "actorId");
      queue.createIndex("oneRevision", ["actorId", "taskId", "expectedRevision"], { unique: true });
    };
    request.onsuccess = () => {
      const db = request.result;
      db.onversionchange = () => { db.close(); opening = undefined; };
      resolve(db);
    };
    request.onerror = () => { opening = undefined; reject(request.error); };
  });

  return opening;
}

function result<T>(request: IDBRequest<T>): Promise<T> {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

function done(transaction: IDBTransaction): Promise<void> {
  return new Promise((resolve, reject) => {
    transaction.oncomplete = () => resolve();
    transaction.onabort = () => reject(transaction.error ?? new Error("IndexedDB transaction aborted"));
  });
}

export async function actorFromDisk(): Promise<OfflineActor | undefined> {
  const db = await database();
  return result(db.transaction("session").objectStore("session").get("active"));
}

export async function rememberActor(actor: OfflineActor): Promise<void> {
  const db = await database();
  const tx = db.transaction("session", "readwrite");
  tx.objectStore("session").put(actor);
  await done(tx);
}

export async function clearActiveDriver(): Promise<void> {
  if (typeof indexedDB === "undefined") return;
  const db = await database();
  const tx = db.transaction(["session", "tasks"], "readwrite");
  const session = tx.objectStore("session");
  const request = session.get("active");
  request.onsuccess = () => {
    const actor: OfflineActor | undefined = request.result;
    session.delete("active");
    if (!actor) return;
    const cursor = tx.objectStore("tasks").index("byActor").openCursor(actor.id);
    cursor.onsuccess = () => {
      if (!cursor.result) return;
      cursor.result.delete();
      cursor.result.continue();
    };
  };
  await done(tx);
}

export async function tasksFromDisk(actorId: string, date: string): Promise<Task[] | undefined> {
  const db = await database();
  const snapshot: Snapshot | undefined = await result(db.transaction("tasks").objectStore("tasks").get([actorId, date]));
  return snapshot?.tasks;
}

export async function saveTasks(snapshot: Snapshot): Promise<void> {
  const db = await database();
  const tx = db.transaction(["session", "tasks"], "readwrite");
  const request = tx.objectStore("session").get("active");
  request.onsuccess = () => {
    if (request.result?.id !== snapshot.actorId) return;
    const store = tx.objectStore("tasks");
    const existing = store.get([snapshot.actorId, snapshot.date]);
    existing.onsuccess = () => {
      const previous = new Map<string, Task>((existing.result as Snapshot | undefined)?.tasks.map((task) => [task.id, task]));
      store.put({ ...snapshot, tasks: snapshot.tasks.map((task) => {
        const before = previous.get(task.id);
        return before && before.revision > task.revision ? before : task;
      }) });
    };
  };
  await done(tx);
}

export async function readQueue(actorId: string): Promise<Queued[]> {
  const db = await database();
  const queue: Queued[] = await result(db.transaction("queue").objectStore("queue").index("byActor").getAll(actorId));
  return queue.sort((a, b) => a.taskId.localeCompare(b.taskId) || a.expectedRevision - b.expectedRevision);
}

export async function addQueued(event: Queued): Promise<void> {
  const db = await database();
  const tx = db.transaction(["session", "queue"], "readwrite");
  const owner = tx.objectStore("session").get("active");
  owner.onsuccess = () => {
    if (owner.result?.id !== event.actorId) { tx.abort(); return; }
    tx.objectStore("queue").add(event);
  };
  await done(tx);
}

export async function markProblem(id: string, problem: string): Promise<void> {
  const db = await database();
  const tx = db.transaction("queue", "readwrite");
  const queue = tx.objectStore("queue");
  const request = queue.get(id);
  request.onsuccess = () => {
    if (request.result) queue.put({ ...request.result, problem });
  };
  await done(tx);
}

export async function acknowledge(entry: Queued, status: Status, revision: number): Promise<void> {
  const db = await database();
  const tx = db.transaction(["queue", "tasks"], "readwrite");
  const tasks = tx.objectStore("tasks");
  const request = tasks.get([entry.actorId, entry.date]);
  request.onsuccess = () => {
    const snapshot: Snapshot | undefined = request.result;
    if (!snapshot) return;
    snapshot.tasks = snapshot.tasks.map((task) => task.id === entry.taskId && task.revision <= revision
      ? { ...task, status, revision } : task);
    tasks.put(snapshot);
  };
  tx.objectStore("queue").delete(entry.id);
  await done(tx);
}
