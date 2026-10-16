// One-time: split the original static page into public/assets/{site.css,logo.png}, templates/site.twig, database/seed/settings.json
import fs from "node:fs";
const h = fs.readFileSync("_orig/Nexova IT Solutions.html", "utf8");
const lines = h.split("\n");

// CSS (lines 8..218, between <style> and </style>)
fs.writeFileSync("public/assets/site.css", lines.slice(7, 218).join("\n") + "\n");

// logo
const logoB64 = h.match(/src="data:image\/png;base64,([^"]+)"/)[1];
fs.writeFileSync("public/assets/logo.png", Buffer.from(logoB64, "base64"));

const S = {
  site_title: "Nexova IT Solutions",
  hero_kicker: "Transform. Innovate. Accelerate.",
  hero_title: "IT solutions that move your business",
  hero_accent: "forward",
  hero_lede: "Nexova designs, builds and runs the software, cloud and security your organization depends on, with the discipline of enterprise IT and the speed of a modern team.",
  hero_check1: "Data Privacy Act aligned", hero_check2: "24/7 support", hero_check3: "Built in the Philippines",
  services_kicker: "Services", services_title: "Everything your IT needs, from one partner",
  services_lede: "Engage us for a single project or hand us the whole stack. Each service stands on its own and works better together.",
  products_kicker: "Products", products_title: "Cloud platforms built for Philippine businesses",
  products_lede: "Subscribe as-is or have us tailor them to your operations. Every product runs on the same security and privacy standards we use for client work.",
  process_kicker: "How we work", process_title: "A clear path from first call to steady state",
  process_lede: "Every engagement follows the same four stages, so you always know what comes next and what you receive.",
  about_kicker: "Why Nexova", about_title: "Enterprise discipline, sized for growing organizations",
  about_lede: "Founded by technology leaders who ran IT for large Philippine companies, Nexova brings the same governance and delivery standards to businesses, LGUs and institutions at a practical cost.",
  industries_kicker: "Industries", industries_title: "Trusted where downtime isn't an option",
  industries_list: "Energy & utilities\nBanking & finance\nHealthcare & clinics\nLocal government units\nManufacturing\nRetail & distribution\nSports & recreation\nBPO & shared services\nEducation",
  contact_kicker: "Get in touch", contact_title: "Let's talk about what you're building",
  contact_lede: "Tell us about your project or the product you'd like to see. A senior consultant replies within one business day, and the first assessment call is free.",
  contact_email: "sales@nexovaph.com", contact_phone: "+63 933 863 1479",
  contact_office: "Philippines · serving clients nationwide", contact_website: "nexovaph.com",
  footer_copyright: "© 2026 Nexova IT Solutions. All rights reserved.",
};
fs.writeFileSync("database/seed/settings.json", JSON.stringify(S, null, 2));

let body = lines.slice(220, 364).join("\n"); // <header> .. </footer>
const rep = (from, to) => { if (!body.includes(from)) throw new Error("missing: " + from.slice(0, 60)); body = body.replace(from, to); };
const t = k => `{{ s.${k} }}`;

body = body.replace(/src="data:image\/png;base64,[^"]+"/g, 'src="/assets/logo.png"');
rep(`<span class="kicker">${S.hero_kicker}</span>`, `<span class="kicker">${t("hero_kicker")}</span>`);
rep(`<h1>IT solutions that move your business <span>forward</span>.</h1>`, `<h1>${t("hero_title")} <span>${t("hero_accent")}</span>.</h1>`);
rep(`<p class="lede">${S.hero_lede}</p>`, `<p class="lede">${t("hero_lede")}</p>`);
for (const [i, k] of [[1, "hero_check1"], [2, "hero_check2"], [3, "hero_check3"]]) rep(`</svg>${S[k]}</span>`, `</svg>${t(k)}</span>`);
for (const p of ["services", "products", "process", "about", "industries", "contact"]) {
  rep(`<span class="kicker">${S[p + "_kicker"]}</span>`, `<span class="kicker">${t(p + "_kicker")}</span>`);
  rep(`<h2>${S[p + "_title"]}</h2>`, `<h2>${t(p + "_title")}</h2>`);
  if (S[p + "_lede"]) {
    const re = p === "contact" ? `<p class="lede">${S.contact_lede}</p>` : `<p>${S[p + "_lede"]}</p>`;
    rep(re, p === "contact" ? `<p class="lede">${t("contact_lede")}</p>` : `<p>${t(p + "_lede")}</p>`);
  }
}
// services cards
body = body.replace(/(<div class="grid3">)[\s\S]*?(\n\s*<\/div>\n\s*<\/div>\n\s*<\/section>\n\n\s*<section id="products")/,
  `$1\n        {% for sv in services %}<article class="card"><div class="icon">{{ sv.icon|raw }}</div><h3>{{ sv.title }}</h3><p>{{ sv.body }}</p></article>\n        {% endfor %}$2`);
// steps
body = body.replace(/(<div class="steps">)[\s\S]*?(\n\s*<\/div>\n\s*<\/div>\n\s*<\/section>\n\n\s*<section id="about")/,
  `$1\n        {% for st in steps %}<div class="step"><div class="step-num">{{ loop.index }}</div><h3>{{ st.title }}</h3><p>{{ st.body }}</p><p class="out">{{ st.deliverable }}</p></div>\n        {% endfor %}$2`);
// chips
body = body.replace(/<div class="chips">[\s\S]*?<\/div>/, `<div class="chips">\n        {% for c in industries %}<span>{{ c }}</span>{% endfor %}\n      </div>`);
// contact info
rep(`<b>${S.contact_email}</b>`, `<b>${t("contact_email")}</b>`);
rep(`<b>${S.contact_phone}</b>`, `<b>${t("contact_phone")}</b>`);
rep(`<b>${S.contact_office}</b>`, `<b>${t("contact_office")}</b>`);
rep(`<b>${S.contact_website}</b>`, `<b>${t("contact_website")}</b>`);
// form: names, csrf, honeypot
body = body.replace('<form id="contact-form" novalidate>', `<form id="contact-form" method="post" action="/contact" novalidate>\n        <input type="hidden" name="{{ csrf.keys.name }}" value="{{ csrf.name }}"><input type="hidden" name="{{ csrf.keys.value }}" value="{{ csrf.value }}">\n        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">`);
for (const [id, n] of [["f-name", "name"], ["f-company", "company"], ["f-email", "email"], ["f-phone", "phone"], ["f-service", "service"], ["f-msg", "message"]])
  rep(`id="${id}"`, `id="${id}" name="${n}"`);
// footer
body = body.replace(/<ul><li><a href="#services">Software development[\s\S]*?<\/ul>/, `<ul>{% for sv in services %}<li><a href="#services">{{ sv.title }}</a></li>{% endfor %}</ul>`);
rep(`<span>${S.footer_copyright}</span><span>sales@nexovaph.com · +63 933 863 1479 · nexovaph.com</span>`,
  `<span>${t("footer_copyright")}</span><span>{{ s.contact_email }} · {{ s.contact_phone }} · {{ s.contact_website }}</span>`);

// script
let script = lines.slice(365, 413).join("\n");
script = script.replace(/var products=\[[\s\S]*?\n\];/, "var products={{ products_json|raw }};");
script = script.replace(/\["Custom software"[^\]]*\]\.concat/, "{{ service_options_json|raw }}.concat");
script = script.replace(/document\.getElementById\('contact-form'\)[\s\S]*<\/script>/, `document.getElementById('contact-form').addEventListener('submit',function(e){
  e.preventDefault();
  var f=e.target,m=document.getElementById('form-msg'),b=f.querySelector('button');
  b.disabled=true;
  fetch(f.action,{method:'POST',body:new FormData(f),headers:{'Accept':'application/json'}})
    .then(function(r){return r.json()})
    .then(function(d){m.textContent=d.message;m.hidden=false;if(d.ok)f.reset();})
    .catch(function(){m.textContent='Something went wrong. Please email us directly.';m.hidden=false;})
    .then(function(){b.disabled=false});
});
</script>`);
if (script.includes("data:image") || script.includes("Preview mode")) throw new Error("script cleanup failed");

const head = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>{{ s.site_title }}</title>
<meta name="description" content="{{ s.hero_lede }}">
<link rel="icon" href="/assets/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Source+Serif+4:wght@500&display=swap">
<link rel="stylesheet" href="/assets/site.css">
</head><body>
`;
fs.writeFileSync("templates/site.twig", head + body + "\n" + script + "\n</body></html>\n");
console.log("ok");
