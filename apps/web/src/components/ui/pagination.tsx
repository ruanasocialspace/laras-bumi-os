import { Button } from "./button";

export function Pagination({ page, last, onChange }: { page: number; last: number; onChange: (page: number) => void }) {
  if (page === 1 && last <= 1) return null;

  return (
    <nav aria-label="Paginasi" className="flex flex-wrap items-center gap-3">
      <Button variant="outline" disabled={page <= 1} onClick={() => onChange(page - 1)}>Sebelumnya</Button>
      <span className="text-sm text-subtle">Halaman {page} dari {last}</span>
      <Button variant="outline" disabled={page >= last} onClick={() => onChange(page + 1)}>Berikutnya</Button>
    </nav>
  );
}
