"""
French narration with Kokoro-82M (voice "ff_siwis", France accent, temporary until an African
French voice exists). The voice is trained on the SIWIS French speech synthesis database
(CC BY 4.0): the YouTube description carries the credit line in demo/licences/voice-models.md.

    demo/work/venv/Scripts/python -I voice/kokoro_fr.py --text "Bonjour." --out a.wav
    demo/work/venv/Scripts/python -I voice/kokoro_fr.py --lines lines.json --out-dir ../work/voice/fr

"[pause 400ms]" markers become silences. To swap the voice later, change VOICE (or point it at
another voice file) and run again; nothing else changes.
"""
import argparse
import json
import os
import re

import numpy as np
import soundfile as sf
import torch

HERE = os.path.dirname(os.path.abspath(__file__))
MODELS = os.path.abspath(os.path.join(HERE, "..", "..", "work", "models", "Kokoro-82M"))
SR = 24000
VOICE = os.path.join(MODELS, "voices", "ff_siwis.pt")


def load():
    from kokoro import KModel, KPipeline

    model = KModel(repo_id="hexgrad/Kokoro-82M",
                   config=os.path.join(MODELS, "config.json"),
                   model=os.path.join(MODELS, "kokoro-v1_0.pth")).eval()
    pipe = KPipeline(lang_code="f", repo_id="hexgrad/Kokoro-82M", model=model)
    return pipe


def speak(pipe, text, speed=1.0):
    # Kokoro is weak on very short fragments: keep each sentence whole (its commas still pause)
    # and honour only the pauses that fall between sentences.
    text = re.sub(r"(?<![.!?])\s*\[pause \d+ms\]\s*", " ", text)
    out = []
    for chunk in re.split(r"(\[pause \d+ms\])", text):
        m = re.match(r"\[pause (\d+)ms\]", chunk)
        if m:
            out.append(np.zeros(int(SR * int(m.group(1)) / 1000), dtype=np.float32))
            continue
        if not chunk.strip():
            continue
        for _, _, audio in pipe(chunk.strip(), voice=VOICE, speed=speed, split_pattern=r"\n+"):
            a = audio.numpy() if isinstance(audio, torch.Tensor) else np.asarray(audio)
            out.append(a.astype(np.float32))
            out.append(np.zeros(int(SR * 0.12), dtype=np.float32))
    return np.concatenate(out) if out else np.zeros(0, dtype=np.float32)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--text")
    ap.add_argument("--out")
    ap.add_argument("--lines")
    ap.add_argument("--out-dir")
    ap.add_argument("--speed", type=float, default=1.0)
    args = ap.parse_args()
    torch.set_num_threads(max(1, (os.cpu_count() or 4) - 2))
    pipe = load()
    if args.text:
        audio = speak(pipe, args.text, args.speed)
        sf.write(args.out, audio, SR, subtype="PCM_16")
        print(f"{args.out}: {len(audio) / SR:.2f} s")
        return
    lines = json.load(open(args.lines, encoding="utf-8"))
    os.makedirs(args.out_dir, exist_ok=True)
    report = []
    for line in lines:
        audio = speak(pipe, line["text"], line.get("speed", args.speed))
        sf.write(os.path.join(args.out_dir, f"{line['id']}.wav"), audio, SR, subtype="PCM_16")
        report.append({"id": line["id"], "seconds": round(len(audio) / SR, 2)})
        print(f"{line['id']}: {len(audio) / SR:.2f} s", flush=True)
    json.dump(report, open(os.path.join(args.out_dir, "report.json"), "w", encoding="utf-8"), indent=1)


if __name__ == "__main__":
    main()
