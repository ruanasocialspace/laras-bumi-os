"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowRight, MapPin } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Brand } from "@/components/brand";
import { Button } from "@/components/ui/button";
import { Notice } from "@/components/ui/feedback";
import { api, ApiError, errorMessage } from "@/lib/api";

const credentials = z.object({
  email: z.email("Masukkan alamat email yang valid."),
  password: z.string().min(1, "Masukkan kata sandi."),
});

type Credentials = z.infer<typeof credentials>;

export default function LoginPage() {
  const router = useRouter();
  const [failure, setFailure] = useState("");
  const { register, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm<Credentials>({
    resolver: zodResolver(credentials),
  });

  async function login(input: Credentials) {
    setFailure("");
    try {
      await api("/auth/login", { method: "POST", body: JSON.stringify(input) });
      router.replace("/dashboard");
    } catch (error) {
      if (error instanceof ApiError && error.fields.email) {
        setError("email", { message: error.fields.email[0] }, { shouldFocus: true });
      } else {
        setFailure(errorMessage(error));
      }
    }
  }

  return (
    <main className="min-h-screen md:grid md:grid-cols-[minmax(300px,0.85fr)_minmax(400px,1.15fr)]">
      <aside className="flex min-h-36 flex-col justify-between gap-10 bg-primary p-6 text-white sm:p-10 md:min-h-screen lg:p-14">
        <Brand />
        <div>
          <div className="mb-6 h-1 w-10 rounded bg-gold" aria-hidden />
          <p className="max-w-sm text-2xl leading-tight font-semibold sm:text-4xl">Operasional pengelolaan sampah</p>
          <p className="mt-4 flex items-center gap-2 text-sm text-white/85"><MapPin aria-hidden className="size-4" />Kabupaten Jember, Jawa Timur</p>
        </div>
      </aside>
      <div className="grid min-h-[65vh] place-items-center p-5 sm:p-10">
        <form className="panel w-full max-w-md space-y-5" onSubmit={handleSubmit(login)} noValidate>
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Masuk ke Laras Bumi OS</h1>
            <p className="mt-2 text-sm text-subtle">Gunakan akun yang diberikan oleh administrator.</p>
          </div>
          {failure && <Notice>{failure}</Notice>}
          <div>
            <label className="field-label" htmlFor="login-email">Email</label>
            <input className="control" id="login-email" type="email" autoComplete="username" aria-invalid={!!errors.email} aria-describedby={errors.email ? "login-email-error" : undefined} {...register("email")} />
            {errors.email && <p id="login-email-error" className="field-error" role="alert">{errors.email.message}</p>}
          </div>
          <div>
            <label className="field-label" htmlFor="login-password">Kata sandi</label>
            <input className="control" id="login-password" type="password" autoComplete="current-password" aria-invalid={!!errors.password} aria-describedby={errors.password ? "login-password-error" : undefined} {...register("password")} />
            {errors.password && <p id="login-password-error" className="field-error" role="alert">{errors.password.message}</p>}
          </div>
          <Button className="w-full justify-between" type="submit" disabled={isSubmitting}>{isSubmitting ? "Memeriksa akun…" : "Masuk"}<ArrowRight aria-hidden className="size-4" /></Button>
        </form>
      </div>
    </main>
  );
}
