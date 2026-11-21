export type Category = { id: string; code: string; name: string };
export type Customer = { id: string; code: string; name: string; status: "active" | "inactive" | "archived"; category_id: string };
export type Contact = { id: string; name: string; phone: string | null; email: string | null };
export type Location = { id: string; name: string; address: string; latitude: string | null; longitude: string | null };
export type Billing = { id: string; billing_name: string; address: string; email: string | null; payment_term_days: number };
export type ContractStatus = "draft" | "active" | "suspended" | "expired" | "terminated";
export type Contract = {
  id: string; customer_id: string; code: string; name: string; starts_on: string; ends_on: string | null;
  status: ContractStatus; effective_status: ContractStatus;
};
export type Service = { id: string; name: string; location_id: string; location_name: string };
export type Rate = {
  id: string; pricing_model: string; unit_rate: string; effective_from: string; effective_until: string | null;
};
export type Schedule = { id: string; service_id: string; weekday: number; slot: string; effective_from: string; effective_until: string | null };
export type ContractEvent = {
  id: string; actor_id: string; from_status: ContractStatus | null; to_status: ContractStatus; reason: string | null; occurred_at: string;
};
