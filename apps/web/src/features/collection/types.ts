export type Status = "scheduled" | "assigned" | "en_route" | "arrived" | "completed" | "failed" | "canceled";
export type DriverStatus = "en_route" | "arrived" | "completed" | "failed";

export const pickupStatus: Record<Status, string> = {
  scheduled: "Terjadwal", assigned: "Ditugaskan", en_route: "Dalam perjalanan", arrived: "Tiba",
  completed: "Selesai", failed: "Gagal", canceled: "Dibatalkan",
};

export type Task = {
  id: string;
  customer_id: string;
  customer_name: string;
  location_name: string;
  address: string;
  service_date: string;
  slot: string;
  status: Status;
  revision: number;
  latitude: string | null;
  longitude: string | null;
  driver_id: string | null;
  driver_name: string | null;
  vehicle_id: string | null;
  vehicle_registration: string | null;
  schedule_id: string | null;
  contract_status?: "draft" | "active" | "suspended" | "expired" | "terminated" | null;
};

export type Event = {
  id: string; type: Status; sequence: number; captured_at: string; reason: string | null;
  weight_kg: string | null; weight_basis: "estimated" | "field_measured" | null;
  evidence_ids: string[];
};

export function jakartaToday(): string {
  return new Intl.DateTimeFormat("sv-SE", { timeZone: "Asia/Jakarta", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());
}

export function mapUrl(task: Pick<Task, "latitude" | "longitude" | "address">): string {
  const destination = task.latitude !== null && task.longitude !== null
    ? task.latitude + "," + task.longitude : task.address;
  return "https://www.google.com/maps/dir/?api=1&destination=" + encodeURIComponent(destination);
}
