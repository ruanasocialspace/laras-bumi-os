"use client";

import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { ClipboardList, LayoutDashboard, LogOut, Settings2 } from "lucide-react";
import { Brand } from "@/components/brand";
import { Button } from "@/components/ui/button";
import { Loading, Notice } from "@/components/ui/feedback";
import { api, ApiError, errorMessage, type Account } from "@/lib/api";
import { cn } from "@/lib/utils";

const ActorContext = createContext<Account | null>(null);

export function useActor() {
  const actor = useContext(ActorContext);
  if (!actor) throw new Error("ActorContext is unavailable");
  return actor;
}

const links = [
  { label: "Dasbor", href: "/dashboard", icon: LayoutDashboard },
  { label: "Pengguna dan peran", href: "/settings/users", icon: Settings2, permission: "identity.manage" },
  { label: "Log audit", href: "/settings/audit", icon: ClipboardList, permission: "audit.read" },
];

export function Workspace({ children }: { children: ReactNode }) {
  const [actor, setActor] = useState<Account | null>(null);
  const [failure, setFailure] = useState("");
  const [signOutError, setSignOutError] = useState("");
  const [reloading, setReloading] = useState(0);
  const [signingOut, setSigningOut] = useState(false);
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    const controller = new AbortController();
    api<{ data: Account }>("/auth/me", { signal: controller.signal })
      .then(({ data }) => { if (!controller.signal.aborted) setActor(data); })
      .catch((error: unknown) => {
        if (controller.signal.aborted) return;
        if (error instanceof ApiError && error.status === 401) {
          setActor(null);
          router.replace("/login");
        } else {
          setFailure(errorMessage(error));
        }
      });
    return () => controller.abort();
  }, [router, pathname, reloading]);

  async function logout() {
    setSigningOut(true);
    setSignOutError("");
    try {
      await api("/auth/logout", { method: "POST" });
      setActor(null);
      router.replace("/login");
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        setActor(null);
        router.replace("/login");
      } else {
        setSignOutError(errorMessage(error));
      }
    } finally {
      setSigningOut(false);
    }
  }

  if (failure) return (
    <main className="mx-auto max-w-xl space-y-4 p-6">
      <Notice>{failure}</Notice>
      <Button onClick={() => { setFailure(""); setReloading((value) => value + 1); }}>Coba lagi</Button>
    </main>
  );
  if (!actor) return <main className="p-6"><Loading>Memeriksa sesi…</Loading></main>;

  return (
    <ActorContext.Provider value={actor}>
      <a href="#main-content" className="sr-only focus:not-sr-only focus:absolute focus:z-10 focus:bg-white focus:p-3">Lewati ke konten</a>
      <div className="min-h-screen lg:grid lg:grid-cols-[252px_minmax(0,1fr)]">
        <aside className="bg-primary px-5 py-5 text-white lg:sticky lg:top-0 lg:h-screen lg:px-5 lg:py-8">
          <Brand />
          <nav aria-label="Navigasi utama" className="mt-5 flex flex-wrap gap-1 lg:mt-11 lg:flex-col">
            {links.filter(({ permission }) => !permission || actor.permissions.includes(permission)).map(({ label, href, icon: Icon }) => (
              <Link href={href} key={href} aria-current={pathname === href ? "page" : undefined}
                className={cn("flex min-h-11 items-center gap-2.5 rounded-lg px-3 text-sm font-medium focus-visible:outline-gold lg:w-full",
                  pathname === href ? "bg-white/15 text-white" : "text-white/85 hover:bg-white/10")}>
                <Icon className="size-4 shrink-0" aria-hidden />{label}
              </Link>
            ))}
          </nav>
        </aside>
        <div>
          <header className="flex items-center justify-between gap-4 border-b border-line bg-white px-5 py-4 sm:px-8">
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold">{actor.name}</p>
              <p className="truncate text-xs text-subtle">{actor.roles.map((role) => role.name).join(", ")}</p>
            </div>
            <Button variant="outline" onClick={logout} disabled={signingOut} className="shrink-0"><LogOut aria-hidden className="size-4" />Keluar</Button>
          </header>
          <main id="main-content" tabIndex={-1} className="mx-auto max-w-7xl px-5 py-7 sm:px-8 lg:py-10">
            {signOutError && <div className="mb-5"><Notice>{signOutError}</Notice></div>}
            {children}
          </main>
        </div>
      </div>
    </ActorContext.Provider>
  );
}
