import { NextResponse } from "next/server";

export const dynamic = "force-dynamic";

export async function GET() {
  const baseUrl = process.env.REI_API_BASE_URL?.trim();
  const token = process.env.REI_API_TOKEN?.trim();

  if (!baseUrl || !token) {
    return NextResponse.json(
      {
        success: false,
        message: "Konfigurasi API belum tersedia.",
      },
      { status: 500 }
    );
  }

  const endpoint = `${baseUrl.replace(/\/+$/, "")}/dashboard.php`;

  try {
    const response = await fetch(endpoint, {
      method: "GET",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
      cache: "no-store",
      signal: AbortSignal.timeout(15000),
    });

    const contentType = response.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
      console.error(
        "API REI mengembalikan respons bukan JSON:",
        response.status
      );

      return NextResponse.json(
        {
          success: false,
          message: "Respons API REI bukan JSON yang valid.",
        },
        { status: 502 }
      );
    }

    const data: unknown = await response.json();

    return NextResponse.json(data, {
      status: response.status,
      headers: {
        "Cache-Control": "no-store, no-cache, must-revalidate",
      },
    });
  } catch (error) {
    console.error(
      "Gagal menghubungi API dashboard REI:",
      error instanceof Error ? error.message : "Kesalahan tidak diketahui"
    );

    return NextResponse.json(
      {
        success: false,
        message: "API REI tidak dapat dihubungi.",
      },
      { status: 502 }
    );
  }
}
