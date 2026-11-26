const UPSTREAM_INDEX_URL =
  "https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/index.html";
const UPSTREAM_LIST_URL =
  "https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/list.json";
const UPSTREAM_COVERS_URL =
  "https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/covers/";
const CACHE_CONTROL = "public, s-maxage=300, stale-while-revalidate=60";
const COVER_NAME_PATTERN = /^[a-zA-Z0-9._-]+\.(?:jpe?g|png|gif|webp|svg)$/i;

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

function rewriteCoverUrls(games) {
  return games.map((game) => {
    if (!game || typeof game.cover !== "string") return game;

    try {
      const coverUrl = new URL(game.cover);
      const coverPath = coverUrl.pathname.match(
        /^\/ert11er\/Offline-HTML-Games-Pack\/master\/covers\/([^/]+)$/,
      );
      const fileName = coverPath?.[1];

      if (
        coverUrl.hostname === "raw.githubusercontent.com" &&
        fileName &&
        COVER_NAME_PATTERN.test(fileName)
      ) {
        return {
          ...game,
          cover: `/api?resource=cover&file=${encodeURIComponent(fileName)}`,
        };
      }
    } catch {
      // Leave unexpected cover URLs unchanged.
    }

    return game;
  });
}

export default async function handler(req, res) {
  const method = req.method ?? "GET";
  if (method !== "GET" && method !== "HEAD") {
    res.setHeader("Allow", "GET, HEAD");
    return res.status(405).send("Method not allowed");
  }

  const resource = req.query?.resource;

  try {
    if (resource === "cover") {
      const fileName = req.query?.file;
      if (typeof fileName !== "string" || !COVER_NAME_PATTERN.test(fileName)) {
        return res.status(400).send("Invalid cover filename");
      }

      const response = await fetch(`${UPSTREAM_COVERS_URL}${encodeURIComponent(fileName)}`, {
        headers: { "user-agent": "Offline-Games-Server/1.0" },
        signal: AbortSignal.timeout(15000),
      });
      if (!response.ok) {
        return res.status(response.status === 404 ? 404 : 502).send("Cover unavailable");
      }

      const contentType = response.headers.get("content-type") || "application/octet-stream";
      if (!contentType.startsWith("image/")) {
        return res.status(502).send("Upstream cover is not an image");
      }

      res.setHeader("Content-Type", contentType);
      res.setHeader("Cache-Control", CACHE_CONTROL);
      return method === "HEAD"
        ? res.status(200).end()
        : res.status(200).send(Buffer.from(await response.arrayBuffer()));
    }

    if (resource === "list") {
      const list = await fetchText(UPSTREAM_LIST_URL);
      const games = JSON.parse(list);
      if (!Array.isArray(games)) {
        return res.status(502).send("Upstream list.json has an invalid format");
      }

      res.setHeader("Content-Type", "application/json; charset=utf-8");
      res.setHeader("Cache-Control", CACHE_CONTROL);
      const rewrittenList = JSON.stringify(rewriteCoverUrls(games));
      return method === "HEAD" ? res.status(200).end() : res.status(200).send(rewrittenList);
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
