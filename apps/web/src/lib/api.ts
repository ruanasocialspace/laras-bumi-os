export type Role = { code: string; name: string };
export type RoleDetails = Role & { permissions: string[] };

export type Account = {
  id: string;
  name: string;
  email: string;
  active: boolean;
  roles: Role[];
  permissions: string[];
};

export type Reference = { currency: string; mass_unit: string; timezone: string };

export type Page<T> = {
  data: T[];
  links: { first: string; last: string; prev: string | null; next: string | null };
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

type ErrorBody = { message: string; fields?: Record<string, string[]> };

export class ApiError extends Error {
  constructor(public readonly status: number, body?: ErrorBody) {
    super(body?.message ?? "Layanan tidak dapat diakses.");
    this.fields = body?.fields ?? {};
  }

  public readonly fields: Record<string, string[]>;
}

function csrfToken(): string {
  const cookie = document.cookie.split("; ").find((entry) => entry.startsWith("XSRF-TOKEN="));
  return cookie ? decodeURIComponent(cookie.slice("XSRF-TOKEN=".length)) : "";
}

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.method && options.method !== "GET") {
    if (path === "/auth/login" || !csrfToken()) {
      const bootstrap = await fetch("/sanctum/csrf-cookie", { cache: "no-store", credentials: "same-origin" });
      if (!bootstrap.ok) throw new ApiError(bootstrap.status);
    }

    headers.set("X-XSRF-TOKEN", csrfToken());
    if (options.body) headers.set("Content-Type", "application/json");
  }

  const response = await fetch("/api/v1" + path, {
    ...options,
    headers,
    cache: "no-store",
    credentials: "same-origin",
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    if (response.status === 419) {
      await fetch("/sanctum/csrf-cookie", { cache: "no-store", credentials: "same-origin" }).catch(() => undefined);
    }
    throw new ApiError(response.status, body?.error);
  }

  if (response.status === 204) return undefined as T;

  return response.json() as Promise<T>;
}

export function errorMessage(error: unknown) {
  return error instanceof ApiError ? error.message : "Koneksi gagal. Silakan coba lagi.";
}
