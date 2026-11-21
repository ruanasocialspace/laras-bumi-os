import { api, ApiError, type Account } from "@/lib/api";
import { acknowledge, actorFromDisk, markProblem, readQueue } from "./offline-store";
import type { Status } from "./types";

type Acknowledgement = { event_id: string; status: Status; revision: number };

export async function synchronize(actorId: string): Promise<number> {
  const run = async () => {
    const { data: actor } = await api<{ data: Account }>("/auth/me");
    if (actor.id !== actorId || !actor.permissions.includes("collections.drive")) throw new ApiError(401);
    let confirmed = 0;
    const blocked = new Set<string>();
    const headers = { "X-Driver-Actor": actorId };
    for (const event of await readQueue(actorId)) {
      if (blocked.has(event.taskId)) continue;
      if ((await actorFromDisk())?.id !== actorId) break;
      const path = "/pickup-tasks/" + event.taskId;
      try {
        for (const evidence of event.files) {
          const body = new FormData();
          body.append("client_file_id", evidence.id);
          body.append("photo", evidence.blob, evidence.name);
          await api(path + "/evidence", { method: "POST", body, headers });
        }
        const { data } = await api<{ data: Acknowledgement }>(path + "/events", {
          method: "POST", body: JSON.stringify(event.payload), headers,
        });
        if (data.event_id !== event.id) throw new Error("Acknowledgement did not match");
        await acknowledge(event, data.status, data.revision);
        confirmed++;
      } catch (error) {
        if (!(error instanceof ApiError) || error.status === 401 || error.status === 419 ||
          error.status === 429 || error.status >= 500) throw error;
        await markProblem(event.id, error.message);
        blocked.add(event.taskId);
      }
    }
    return confirmed;
  };

  return navigator.locks
    ? navigator.locks.request("laras-driver-sync:" + actorId, { ifAvailable: true }, (lock) => lock ? run() : 0)
    : run();
}
