const UPSTREAM_INDEX_URL =
  "https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/index.html";
const UPSTREAM_LIST_URL =
  "https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/list.json";
const CACHE_CONTROL = "public, s-maxage=300, stale-while-revalidate=60";

async function fetchText(url) {
  const response = await fetch(url, {
    headers: { "user-agent": "Offline-Games-Server/1.0" },
    signal: AbortSignal.timeout(15000),
  });

  if (!response.ok) {
    throw new Error(`Upstream request failed with status ${response.status}`);
  }

  const text = await response.text();
  if (!text.trim()) {
    throw new Error("Upstream returned an empty response");
  }

  return text;
}

export default async function handler(req, res) {
  const method = req.method ?? "GET";
  if (method !== "GET" && method !== "HEAD") {
    res.setHeader("Allow", "GET, HEAD");
    return res.status(405).send("Method not allowed");
  }

  const resource = req.query?.resource;

  try {
    if (resource === "list") {
      const list = await fetchText(UPSTREAM_LIST_URL);
      if (!Array.isArray(JSON.parse(list))) {
        return res.status(502).send("Upstream list.json has an invalid format");
      }

      res.setHeader("Content-Type", "application/json; charset=utf-8");
      res.setHeader("Cache-Control", CACHE_CONTROL);
      return method === "HEAD" ? res.status(200).end() : res.status(200).send(list);
    }

    if (resource) {
      return res.status(404).send("Not found");
    }

    const [html, list] = await Promise.all([
      fetchText(UPSTREAM_INDEX_URL),
      fetchText(UPSTREAM_LIST_URL),
    ]);
    if (!Array.isArray(JSON.parse(list))) {
      return res.status(502).send("Upstream list.json has an invalid format");
    }

    const rewrittenHtml = html.replaceAll(UPSTREAM_LIST_URL, "/list.json");
    res.setHeader("Content-Type", "text/html; charset=utf-8");
    res.setHeader("Cache-Control", CACHE_CONTROL);
    return method === "HEAD"
      ? res.status(200).end()
      : res.status(200).send(rewrittenHtml);
  } catch (error) {
    console.error("Failed to serve the games pack:", error);
    return res.status(502).send("Unable to fetch the games pack from its upstream source");
  }
}
