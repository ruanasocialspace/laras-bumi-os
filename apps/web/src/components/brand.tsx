import { Leaf } from "lucide-react";

export function Brand() {
  return (
    <div className="flex items-center gap-3">
      <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-gold text-primary"><Leaf aria-hidden className="size-5" /></span>
      <span>
        <span className="block text-[15px] leading-tight font-bold">Laras Bumi OS</span>
        <span className="text-xs opacity-85">CV Laras Bumi Grup</span>
      </span>
    </div>
  );
}
