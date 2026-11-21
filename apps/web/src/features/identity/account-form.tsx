"use client";

import { useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Notice } from "@/components/ui/feedback";
import { api, ApiError, errorMessage, type Account, type RoleDetails } from "@/lib/api";

const inputFields = {
  name: z.string().trim().min(1, "Nama wajib diisi.").max(120, "Nama maksimal 120 karakter."),
  email: z.email("Masukkan alamat email yang valid.").max(254, "Email maksimal 254 karakter."),
  active: z.boolean(),
  role_codes: z.array(z.string()).min(1, "Pilih minimal satu peran."),
};
const passwordRule = z.string().min(12, "Kata sandi minimal 12 karakter.")
  .refine((value) => new TextEncoder().encode(value).length <= 72, "Kata sandi maksimal 72 byte.");
const createSchema = z.object({ ...inputFields, password: passwordRule });
const updateSchema = z.object({ ...inputFields, password: z.union([z.literal(""), passwordRule]) });
type AccountFields = z.infer<typeof updateSchema>;

export function AccountForm({ account, roles, onCancel, onSave }: {
  account: Account | null;
  roles: RoleDetails[];
  onCancel: () => void;
  onSave: (saved: Account) => void;
}) {
  const [failure, setFailure] = useState("");
  const { register, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm<AccountFields>({
    resolver: zodResolver(account ? updateSchema : createSchema),
    defaultValues: {
      name: account?.name ?? "",
      email: account?.email ?? "",
      password: "",
      active: account?.active ?? true,
      role_codes: account?.roles.map((role) => role.code) ?? [],
    },
  });

  async function save(input: AccountFields) {
    setFailure("");
    const payload = {
      name: input.name,
      email: input.email,
      active: input.active,
      role_codes: input.role_codes,
      ...(input.password ? { password: input.password } : {}),
    };
    try {
      const { data } = await api<{ data: Account }>(account ? "/users/" + account.id : "/users", {
        method: account ? "PATCH" : "POST",
        body: JSON.stringify(payload),
      });
      onSave(data);
    } catch (error) {
      if (error instanceof ApiError) {
        for (const field of ["name", "email", "password", "role_codes", "active"] as const) {
          const message = error.fields[field]?.[0];
          if (message) setError(field, { message });
        }
      }
      setFailure(errorMessage(error));
    }
  }

  return (
    <form className="panel space-y-5" onSubmit={handleSubmit(save)} noValidate aria-labelledby="account-title">
      <div>
        <h2 id="account-title" className="text-lg font-semibold">{account ? "Ubah pengguna" : "Tambah pengguna"}</h2>
        {account && <p className="mt-1 text-sm text-subtle">Isi kata sandi hanya saat perlu menggantinya.</p>}
      </div>
      {failure && <Notice>{failure}</Notice>}
      <div className="grid gap-4 md:grid-cols-2">
        <div>
          <label className="field-label" htmlFor="account-name">Nama</label>
          <input id="account-name" className="control" autoComplete="off" aria-invalid={!!errors.name} aria-describedby={errors.name ? "account-name-error" : undefined} {...register("name")} />
          {errors.name && <p id="account-name-error" className="field-error">{errors.name.message}</p>}
        </div>
        <div>
          <label className="field-label" htmlFor="account-email">Email</label>
          <input id="account-email" className="control" type="email" autoComplete="off" aria-invalid={!!errors.email} aria-describedby={errors.email ? "account-email-error" : undefined} {...register("email")} />
          {errors.email && <p id="account-email-error" className="field-error">{errors.email.message}</p>}
        </div>
        <div>
          <label className="field-label" htmlFor="account-password">Kata sandi</label>
          <input id="account-password" className="control" type="password" autoComplete="new-password" aria-invalid={!!errors.password} aria-describedby={errors.password ? "account-password-error" : undefined} {...register("password")} />
          {errors.password && <p id="account-password-error" className="field-error">{errors.password.message}</p>}
        </div>
        <label className="flex items-center gap-2 self-end py-3 text-sm font-medium">
          <input type="checkbox" className="size-4 accent-primary" {...register("active")} />Akun aktif
        </label>
      </div>
      <fieldset className="space-y-2" aria-describedby={errors.role_codes ? "account-role-error" : undefined}>
        <legend className="field-label">Peran</legend>
        <div className="grid gap-2 sm:grid-cols-2">
          {roles.map((role) => (
            <label key={role.code} className="flex min-h-10 items-center gap-2 rounded-lg border border-line px-3 text-sm">
              <input type="checkbox" className="size-4 accent-primary" value={role.code} {...register("role_codes")} />{role.name}
            </label>
          ))}
        </div>
        {errors.role_codes && <p id="account-role-error" className="field-error">{errors.role_codes.message}</p>}
      </fieldset>
      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={isSubmitting}>{isSubmitting ? "Menyimpan…" : "Simpan pengguna"}</Button>
        <Button variant="outline" onClick={onCancel} disabled={isSubmitting}>Batal</Button>
      </div>
    </form>
  );
}
