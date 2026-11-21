import { CustomerDetail } from "@/features/management/customer-detail";

export default async function CustomerPage({ params }: { params: Promise<{ id: string }> }) {
  return <CustomerDetail id={(await params).id} />;
}
