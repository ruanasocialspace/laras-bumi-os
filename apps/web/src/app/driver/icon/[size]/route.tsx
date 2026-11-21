import { ImageResponse } from "next/og";

export async function GET(_request: Request, context: RouteContext<"/driver/icon/[size]">) {
  const { size } = await context.params;
  if (size !== "192" && size !== "512") return new Response(null, { status: 404 });
  const pixels = Number(size);
  return new ImageResponse(
    <div style={{
      background: "#174D3B", color: "#E3CC98", display: "flex", alignItems: "center",
      justifyContent: "center", width: "100%", height: "100%", fontSize: pixels * 0.32, fontWeight: 700,
    }}>LB</div>,
    { width: pixels, height: pixels },
  );
}
