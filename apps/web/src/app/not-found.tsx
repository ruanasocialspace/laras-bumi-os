import Link from "next/link";

export default function NotFound() {
  return <main className="mx-auto max-w-lg space-y-4 p-6"><h1 className="text-2xl font-semibold">Halaman tidak ditemukan</h1><Link className="text-primary underline" href="/dashboard">Kembali ke dasbor</Link></main>;
}
