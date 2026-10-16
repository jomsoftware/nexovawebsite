// One-time: pull content out of the original static page into database/seed/*.json (+ logo files).
import fs from "node:fs";
const h = fs.readFileSync("_orig/Nexova IT Solutions.html", "utf8");
const products = eval(h.match(/var products=(\[[\s\S]*?\n\]);/)[1]);
const ext = { "image/jpeg": "jpg", "image/png": "png", "image/svg+xml": "svg" };
const out = products.map((p, i) => {
  let logo = null;
  if (p.logo) {
    const m = p.logo.match(/^data:([^;]+);base64,(.*)$/s);
    logo = `${p.id}.${ext[m[1]]}`;
    fs.writeFileSync(`database/seed/logos/${logo}`, Buffer.from(m[2], "base64"));
  }
  return { slug: p.id, name: p.name, name_html: p.nameHtml ?? null, category: p.cat, audience: p.aud ?? "",
    description: p.desc, url: p.url ?? null, mark: p.mark, mark_bg: p.markBg ?? null, mark_class: p.markClass ?? null,
    logo, sort: i, features: p.f };
});
const strip = s => s.replace(/<[^>]+>/g, "").trim();
const services = [...h.matchAll(/<article class="card"><div class="icon">(<svg[\s\S]*?<\/svg>)<\/div><h3>([\s\S]*?)<\/h3>([\s\S]*?)<\/article>/g)]
  .map((m, i) => ({ icon: m[1], title: strip(m[2]), body: strip(m[3]), sort: i }));
const steps = [...h.matchAll(/<div class="step"><div class="step-num">\d<\/div><h3>(.*?)<\/h3><p>(.*?)<\/p><p class="out">(.*?)<\/p>/g)]
  .map((m, i) => ({ title: m[1], body: m[2], deliverable: m[3], sort: i }));
fs.writeFileSync("database/seed/content.json", JSON.stringify({ products: out, services, steps }, null, 2));
console.log(out.length, "products", services.length, "services", steps.length, "steps");
