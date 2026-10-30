"""
Downloads the voice models into demo/work/models, pinned to exact revisions, and checks
fingerprints where we have them. Re-running skips files that are already complete.

    demo/work/venv/Scripts/python -I voice/fetch_models.py

Sources and licences (recorded in demo/licences/voice-models.md):
- saheedniyi/YarnGPT2b (Apache-2.0): the Nigerian-accented English voice model (voice "idera").
- novateur/WavTokenizer-large-speech-75token (MIT), revision 1cc9faee: the audio decoder.
  This exact file: the newer v2 checkpoint garbles YarnGPT's speech.
- hexgrad/Kokoro-82M (Apache-2.0): the French voice model and voice "ff_siwis" (trained on the
  SIWIS French speech synthesis database, CC BY 4.0: credit required).
"""
import hashlib
import os
import sys

from huggingface_hub import hf_hub_download, snapshot_download

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", "work", "models"))

PINS = {
    "yarn": ("saheedniyi/YarnGPT2b", "eabc85c6d0ed44e4691d59dafc32d4255c215883"),
    "wavtok": ("novateur/WavTokenizer-large-speech-75token", "1cc9faee31025548fbae6ffe11115d7207093638"),
    "kokoro": ("hexgrad/Kokoro-82M", None),  # resolved to a commit and recorded on first run
}
WAVTOK_FILE = "wavtokenizer_large_speech_320_24k.ckpt"
WAVTOK_SHA256 = "7450020c154f6aba033cb8651466cb79cb1b1cdd10ea64eaba68e7871cabcc5a"


def sha256(path):
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(1 << 22), b""):
            h.update(chunk)
    return h.hexdigest()


def main():
    os.makedirs(ROOT, exist_ok=True)

    repo, rev = PINS["yarn"]
    path = snapshot_download(repo, revision=rev, local_dir=os.path.join(ROOT, "YarnGPT2b"),
                             allow_patterns=["*.json", "*.txt", "*.safetensors"])
    print("YarnGPT2b:", path, flush=True)

    repo, rev = PINS["wavtok"]
    ckpt = hf_hub_download(repo, WAVTOK_FILE, revision=rev, local_dir=os.path.join(ROOT, "WavTokenizer"))
    got = sha256(ckpt)
    if got != WAVTOK_SHA256:
        os.remove(ckpt)
        sys.exit(f"WavTokenizer checkpoint fingerprint mismatch ({got}); file removed.")
    print("WavTokenizer:", ckpt, "sha256 ok", flush=True)

    repo, rev = PINS["kokoro"]
    kdir = os.path.join(ROOT, "Kokoro-82M")
    for f in ["config.json", "kokoro-v1_0.pth", "voices/ff_siwis.pt"]:
        p = hf_hub_download(repo, f, revision=rev, local_dir=kdir)
        print("Kokoro:", p, flush=True)


if __name__ == "__main__":
    main()
