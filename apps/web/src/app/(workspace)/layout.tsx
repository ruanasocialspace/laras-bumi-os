import { Workspace } from "@/features/session/workspace";

export default function WorkspaceLayout({ children }: { children: React.ReactNode }) {
  return <Workspace>{children}</Workspace>;
}
