"""
English narration with YarnGPT2b (Nigerian-accented English, voice "idera").

YarnGPT says long passages badly, so we never give it one: each sentence is generated on its
own, several takes per sentence, and the best take is kept by objective checks (it finished on
its own, its length suits its words, no long silence inside, no clipping). The takes are then
joined with natural pauses and matched in loudness, so the result reads as one delivery.

    demo/work/venv/Scripts/python -I voice/yarn.py --text "Line one. Line two." --out a.wav
    demo/work/venv/Scripts/python -I voice/yarn.py --lines lines.json --out-dir ../work/voice/en

lines.json: [{"id": "s1", "text": "...", "pause_after_ms": 400}, ...] where text may contain
"[pause 400ms]" markers. Deterministic: seeds come from the sentence text and take number.
"""
import argparse
import hashlib
import json
import os
import re
import sys

import numpy as np
import soundfile as sf
import torch

HERE = os.path.dirname(os.path.abspath(__file__))
WORK = os.path.abspath(os.path.join(HERE, "..", "..", "work"))
RT = os.path.join(WORK, "vendor", "yarn_rt")
MODELS = os.path.join(WORK, "models")
sys.path.insert(0, RT)

SR = 24000
VOICE = "idera"


def load():
    from yarn_audiotokenizer import AudioTokenizerV2  # vendored from yarngpt 0.2.0 (MIT)
    from transformers import AutoModelForCausalLM

    yarn = os.path.join(MODELS, "YarnGPT2b")
    cfg = os.path.join(MODELS, "wavtokenizer_mediumdata_frame75_3s_nq1_code4096_dim512_kmeans200_attn.yaml")
    ckpt = os.path.join(MODELS, "WavTokenizer", "wavtokenizer_large_speech_320_24k.ckpt")
    tok = AudioTokenizerV2(yarn, ckpt, cfg)
    model = AutoModelForCausalLM.from_pretrained(yarn, torch_dtype="auto").to(tok.device)
    model.eval()
    return tok, model


def seed_for(text, take):
    return int(hashlib.sha256(f"{text}|{take}".encode()).hexdigest()[:8], 16)


def generate(tok, model, text, take):
    """One take of one sentence. Take 0 is the model card's near-greedy setting."""
    torch.manual_seed(seed_for(text, take))
    prompt = tok.create_prompt(text, lang="english", speaker_name=VOICE)
    ids = tok.tokenize_prompt(prompt)
    with torch.inference_mode():
        out = model.generate(
            input_ids=ids,
            do_sample=True,
            temperature=0.1 if take == 0 else 0.1 + 0.08 * take,
            top_p=0.95,
            repetition_penalty=1.1,
            max_length=4000,
        )
    finished = out.shape[1] < 4000
    codes = tok.get_codes(out)
    if not codes:
        return None, finished
    audio = tok.get_audio(codes).squeeze().numpy().astype(np.float32)
    return audio, finished


def trim(audio, thresh_db=-42.0, pad_ms=40):
    """Cut leading and trailing silence, keeping a short natural pad."""
    frame = int(0.01 * SR)
    n = len(audio) // frame
    if n == 0:
        return audio
    rms = np.sqrt(np.mean(audio[: n * frame].reshape(n, frame) ** 2, axis=1) + 1e-12)
    db = 20 * np.log10(rms + 1e-12)
    voiced = np.where(db > thresh_db)[0]
    if len(voiced) == 0:
        return audio[:0]
    pad = int(pad_ms / 10)
    a = max(0, voiced[0] - pad) * frame
    b = min(n, voiced[-1] + 1 + pad) * frame
    return audio[a:b]


def longest_gap_s(audio, thresh_db=-42.0):
    frame = int(0.01 * SR)
    n = len(audio) // frame
    if n == 0:
        return 0.0
    rms = np.sqrt(np.mean(audio[: n * frame].reshape(n, frame) ** 2, axis=1) + 1e-12)
    silent = 20 * np.log10(rms + 1e-12) <= thresh_db
    best = run = 0
    for s in silent:
        run = run + 1 if s else 0
        best = max(best, run)
    return best / 100.0


def score(audio, finished, words):
    """Lower is better. Hard failures score infinity."""
    if audio is None or len(audio) < SR * 0.3:
        return float("inf"), {}
    a = trim(audio)
    dur = len(a) / SR
    # Measured on idera: steady narration is about 2.4 to 2.9 words a second.
    expected = words / 2.6
    gap = longest_gap_s(a)
    peak = float(np.max(np.abs(a))) if len(a) else 0.0
    s = abs(dur - expected) / max(expected, 0.5)
    if not finished:
        s += 5
    if gap > 0.7:
        s += 2 + gap
    if dur > expected * 1.6 or dur < expected * 0.55:
        s += 3
    if peak >= 0.999:
        s += 1
    return s, {"dur": round(dur, 2), "expected": round(expected, 2), "gap": gap, "finished": finished}


def loudness_match(audio, target_rms_db=-20.0):
    frame = int(0.02 * SR)
    n = len(audio) // frame
    if n == 0:
        return audio
    rms = np.sqrt(np.mean(audio[: n * frame].reshape(n, frame) ** 2, axis=1))
    active = rms[rms > np.max(rms) * 0.1]
    level = 20 * np.log10(np.sqrt(np.mean(active ** 2)) + 1e-12) if len(active) else -60
    out = audio * (10 ** ((target_rms_db - level) / 20))
    peak = np.max(np.abs(out))
    return out / peak * 0.97 if peak > 0.97 else out


def split_sentences(text):
    """Sentences and explicit pauses: returns [("say", s) | ("pause", ms)].
    A pause inside a sentence is dropped: YarnGPT says short fragments badly, so a sentence is
    always generated whole; pauses between sentences are kept at their marked length."""
    text = re.sub(r"(?<![.!?])\s*\[pause \d+ms\]\s*", " ", text)
    # Letter abbreviations (A.I., C.O.O.) are read as letters and never end a sentence.
    text = re.sub(r"\b((?:[A-Z]\.){2,})", lambda m: m.group(1).replace(".", " ").strip() + " ", text)
    text = re.sub(r" +", " ", text)
    parts = []
    for chunk in re.split(r"(\[pause \d+ms\])", text):
        m = re.match(r"\[pause (\d+)ms\]", chunk)
        if m:
            parts.append(("pause", int(m.group(1))))
            continue
        for s in re.split(r"(?<=[.!?])\s+", chunk.strip()):
            if s.strip():
                parts.append(("say", s.strip()))
    return parts


def speak(tok, model, text, takes=3, log=None):
    out = []
    plan = split_sentences(text)
    for i, (kind, val) in enumerate(plan):
        if kind == "pause":
            out.append(np.zeros(int(SR * val / 1000), dtype=np.float32))
            continue
        words = len(re.findall(r"[A-Za-z0-9']+", val))
        best = None
        for t in range(takes):
            audio, finished = generate(tok, model, val, t)
            s, info = score(audio, finished, words)
            if log is not None:
                log.append({"sentence": val, "take": t, "score": round(s, 3), **info})
            if best is None or s < best[0]:
                best = (s, audio)
            if s < 0.12:  # good enough; save time
                break
        if best is None or best[1] is None or best[0] == float("inf"):
            raise RuntimeError(f"No usable take for: {val}")
        out.append(loudness_match(trim(best[1])))
        nxt = plan[i + 1][0] if i + 1 < len(plan) else None
        if nxt == "say":
            out.append(np.zeros(int(SR * 0.28), dtype=np.float32))  # between sentences
    return np.concatenate(out) if out else np.zeros(0, dtype=np.float32)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--text")
    ap.add_argument("--out")
    ap.add_argument("--lines")
    ap.add_argument("--out-dir")
    ap.add_argument("--takes", type=int, default=3)
    args = ap.parse_args()
    torch.set_num_threads(max(1, (os.cpu_count() or 4) - 2))
    tok, model = load()
    if args.text:
        log = []
        audio = speak(tok, model, args.text, args.takes, log)
        sf.write(args.out, audio, SR, subtype="PCM_16")
        print(json.dumps(log, indent=1))
        print(f"{args.out}: {len(audio) / SR:.2f} s")
        return
    lines = json.load(open(args.lines, encoding="utf-8"))
    os.makedirs(args.out_dir, exist_ok=True)
    report = []
    for line in lines:
        path = os.path.join(args.out_dir, f"{line['id']}.wav")
        if os.path.exists(path) and not os.environ.get("REDO"):
            print(f"{line['id']}: kept", flush=True)  # resume: lines already made stay as they are
            continue
        log = []
        audio = speak(tok, model, line["text"], args.takes, log)
        sf.write(path, audio, SR, subtype="PCM_16")
        report.append({"id": line["id"], "seconds": round(len(audio) / SR, 2), "takes": log})
        print(f"{line['id']}: {len(audio) / SR:.2f} s", flush=True)
    json.dump(report, open(os.path.join(args.out_dir, "report.json"), "w", encoding="utf-8"), indent=1)


if __name__ == "__main__":
    main()
