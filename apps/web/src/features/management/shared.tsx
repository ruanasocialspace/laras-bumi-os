"use client";

import { useEffect, useId, useState, type ReactNode } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Loading, Notice } from "@/components/ui/feedback";
import { Pagination } from "@/components/ui/pagination";
import { api, ApiError, errorMessage, type Page } from "@/lib/api";

export type Option = { value: string; label: string };
type Value = string | boolean;
export type Values = Record<string, Value>;
export type Field = {
  name: string;
  label: string;
  type?: "text" | "email" | "tel" | "date" | "time" | "number" | "textarea" | "checkbox";
  options?: Option[];
  required?: boolean;
  max?: number;
  inputMode?: "decimal" | "numeric";
};

export const customerStatus: Record<string, string> = {
  active: "Aktif", inactive: "Nonaktif", archived: "Diarsipkan",
};
export const contractStatus: Record<string, string> = {
  draft: "Draf", active: "Aktif", suspended: "Ditangguhkan", expired: "Kedaluwarsa", terminated: "Diakhiri",
};

export function options(labels: Record<string, string>): Option[] {
  return Object.entries(labels).map(([value, label]) => ({ value, label }));
}

export function useRecords<T>(path: string, headers?: Record<string, string>) {
  const [revision, setRevision] = useState(0);
  const requestHeaders = JSON.stringify(headers ?? {});
  const key = path + "#" + requestHeaders + "#" + revision;
  const [result, setResult] = useState<{ key: string; data?: T; error?: string } | null>(null);
  useEffect(() => {
    if (!path) return;
    const controller = new AbortController();
    api<T>(path, { signal: controller.signal, headers: JSON.parse(requestHeaders) as Record<string, string> })
      .then((data) => { if (!controller.signal.aborted) setResult({ key, data }); })
      .catch((failure: unknown) => {
        if (!controller.signal.aborted) setResult({ key, error: errorMessage(failure) });
      });
    return () => controller.abort();
  }, [key, path, requestHeaders]);

  const current = result?.key === key ? result : null;
  return { data: current?.data, error: current?.error, refresh: () => setRevision((count) => count + 1) };
}

export function DataError({ error, onRetry }: { error: string; onRetry: () => void }) {
  return <div className="space-y-3"><Notice>{error}</Notice><Button variant="outline" onClick={onRetry}>Coba lagi</Button></div>;
}

export function Records<T extends { id: string }>({ path, empty, render, headers }: {
  path: string; empty: string; render: (row: T) => ReactNode; headers?: Record<string, string>;
}) {
  const [page, setPage] = useState(1);
  const { data, error, refresh } = useRecords<Page<T>>(path
    + (path.includes("?") ? "&" : "?") + "page=" + page + "&per_page=15", headers);
  return <div className="space-y-4">
    {error ? <DataError error={error} onRetry={refresh} />
      : !data ? <Loading />
      : data.data.length === 0 ? <p className="text-sm text-subtle">{empty}</p>
      : <ul className="divide-y divide-line">{data.data.map((row) => <li className="py-3" key={row.id}>{render(row)}</li>)}</ul>}
    {data && <Pagination page={page} last={data.meta.last_page} onChange={setPage} />}
  </div>;
}

export function Editor<T>({ title, fields, initial = {}, path, method = "POST", onSaved, normalize, onCancel }: {
  title: string;
  fields: Field[];
  initial?: Values;
  path: string;
  method?: "POST" | "PATCH" | "PUT";
  onSaved: (data: T) => void;
  normalize?: (values: Values) => object;
  onCancel?: () => void;
}) {
  const id = useId();
  const [failure, setFailure] = useState<string[]>([]);
  const schema = z.record(z.string(), z.union([z.string(), z.boolean()])).superRefine((values, context) => {
    for (const field of fields) {
      const value = values[field.name];
      if (field.required && (typeof value !== "string" || value.trim() === "")) {
        context.addIssue({ code: "custom", path: [field.name], message: "Kolom ini wajib diisi." });
      }
      if (typeof value === "string" && value && field.type === "email" && !z.email().safeParse(value).success) {
        context.addIssue({ code: "custom", path: [field.name], message: "Masukkan alamat email yang valid." });
      }
    }
  });
  const { register, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: Object.fromEntries(fields.map((field) => [field.name, initial[field.name] ?? (field.type === "checkbox" ? false : "")])),
  });

  async function submit(values: Values) {
    setFailure([]);
    try {
      const { data } = await api<{ data: T }>(path, { method, body: JSON.stringify(normalize?.(values) ?? values) });
      onSaved(data);
    } catch (error) {
      const messages = [errorMessage(error)];
      if (error instanceof ApiError) {
        for (const [name, issues] of Object.entries(error.fields)) {
          if (fields.some((field) => field.name === name)) setError(name, { message: issues[0] });
          else messages.push(...issues);
        }
      }
      setFailure(messages);
    }
  }

  return <form className="space-y-4 rounded-xl border border-line bg-background p-4" onSubmit={handleSubmit(submit)} noValidate aria-label={title}>
    <h3 className="font-semibold">{title}</h3>
    {failure.map((message) => <Notice key={message}>{message}</Notice>)}
    <div className="grid gap-4 sm:grid-cols-2">
      {fields.map((field) => {
        const fieldId = id + "-" + field.name;
        const error = errors[field.name];
        const props = {
          id: fieldId,
          "aria-invalid": !!error,
          "aria-describedby": error ? fieldId + "-error" : undefined,
          ...register(field.name),
        };

        return <div key={field.name}>
          {field.type === "checkbox" ? <label htmlFor={fieldId} className="flex min-h-11 items-center gap-2 text-sm font-medium">
            <input {...props} type="checkbox" className="size-4 accent-primary" />{field.label}
          </label> : <>
            <label htmlFor={fieldId} className="field-label">{field.label}</label>
            {field.options ? <select {...props} className="control">
              <option value="">Pilih…</option>
              {field.options.map(({ value, label }) => <option key={value} value={value}>{label}</option>)}
            </select> : field.type === "textarea" ? <textarea {...props} maxLength={field.max} className="control min-h-20" />
              : <input {...props} type={field.type ?? "text"} inputMode={field.inputMode} maxLength={field.max} className="control" />}
          </>}
          {error?.message && <p id={fieldId + "-error"} className="field-error" role="alert">{error.message}</p>}
        </div>;
      })}
    </div>
    <div className="flex flex-wrap gap-2">
      <Button type="submit" disabled={isSubmitting}>{isSubmitting ? "Menyimpan…" : "Simpan"}</Button>
      {onCancel && <Button variant="outline" onClick={onCancel} disabled={isSubmitting}>Batal</Button>}
    </div>
  </form>;
}

const jakartaDate = new Intl.DateTimeFormat("id-ID", { dateStyle: "medium", timeZone: "Asia/Jakarta" });
const jakartaTime = new Intl.DateTimeFormat("id-ID", { dateStyle: "medium", timeStyle: "short", timeZone: "Asia/Jakarta" });
export function dateLabel(date: string | null) {
  return date ? jakartaDate.format(new Date(date + "T00:00:00+07:00")) : "Tanpa tanggal akhir";
}

export function timeLabel(timestamp: string) {
  return jakartaTime.format(new Date(timestamp)) + " WIB";
}

export function rupiah(rate: string) {
  const [whole, fraction = ""] = rate.split(".");
  const suffix = fraction.replace(/0+$/, "");
  return "Rp" + BigInt(whole).toLocaleString("id-ID") + (suffix ? "," + suffix : "");
}
