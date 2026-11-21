import { AlertCircle, CheckCircle2, LoaderCircle } from "lucide-react";
import type { ReactNode } from "react";

export function Loading({ children = "Memuat data…" }: { children?: ReactNode }) {
  return <p role="status" className="flex items-center gap-2 text-sm text-subtle"><LoaderCircle aria-hidden className="size-4 animate-spin" />{children}</p>;
}

export function Notice({ children, success = false }: { children: ReactNode; success?: boolean }) {
  const Icon = success ? CheckCircle2 : AlertCircle;
  const color = success ? "border-green-200 bg-green-50 text-green-900" : "border-red-200 bg-red-50 text-red-900";

  return <div role={success ? "status" : "alert"} className={"flex items-start gap-2 rounded-lg border p-3 text-sm " + color}><Icon aria-hidden className="mt-0.5 size-4 shrink-0" />{children}</div>;
}
