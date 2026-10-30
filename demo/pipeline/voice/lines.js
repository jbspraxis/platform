/**
 * Extracts the spoken lines of one cut and language from demo/voiceover.json into the
 * {id, text} list that voice/yarn.py and voice/kokoro_fr.py read.
 *   node voice/lines.js <60s|3min> <en|fr> <out.json>
 */
const fs = require("fs");
const path = require("path");
const [cutId, lang, out] = process.argv.slice(2);
const v = JSON.parse(fs.readFileSync(path.join(__dirname, "..", "..", "voiceover.json"), "utf8"));
const cut = v.cuts.find((c) => c.id === cutId);
if (!cut) throw new Error(`No cut ${cutId}`);
const lines = [];
for (const shot of cut.shots || cut.chapters) for (const l of shot.lines) lines.push({ id: l.id, text: l[lang].spoken, start: l.start, end_by: l.end_by });
fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, JSON.stringify(lines, null, 1));
console.log(`${lines.length} lines -> ${out}`);
