"""
An original, quiet music bed for the narrated demo videos, composed by this script.

Why original: a bed we make ourselves belongs to JBS Praxis outright, so it can never draw a
YouTube Content ID claim or an attribution question (runbook section 8 asks for a licence that
allows commercial use on the website and YouTube without attribution issues).

What it is: slow, warm, unhurried. Extended chords on a soft pad, a sparse electric-piano line,
a low sustained bass, all in a synthetic hall. No drums, no melody to compete with the voice.
It sits about 24 dB under the narration and is ducked further whenever someone speaks.

    python music/compose.py --seconds 200 --out ../work/music/bed.wav

Deterministic: the same arguments always give the same file.
"""
import argparse
import math
import wave

import numpy as np

SR = 48000
BPM = 66
BEAT = 60.0 / BPM
BAR = 4 * BEAT

# D major colour, never dark: Dmaj9, Bm9, Gmaj9 (add 13), A6sus. MIDI note numbers.
PROGRESSION = [
    {"root": 38, "pad": [62, 66, 69, 73, 76], "keys": [74, 76, 78, 81]},   # Dmaj9
    {"root": 35, "pad": [59, 62, 66, 69, 73], "keys": [71, 73, 74, 78]},   # Bm9
    {"root": 31, "pad": [55, 59, 62, 66, 69], "keys": [69, 71, 74, 76]},   # Gmaj9
    {"root": 33, "pad": [57, 62, 64, 66, 69], "keys": [69, 71, 73, 76]},   # A6sus
]
BARS_PER_CHORD = 2


def hz(note):
    return 440.0 * 2 ** ((note - 69) / 12.0)


def envelope(n, attack, release, sustain_level=1.0):
    """Linear-in-dB-ish envelope: smooth attack and release, no clicks."""
    a = max(1, int(attack * SR))
    r = max(1, int(release * SR))
    env = np.full(n, sustain_level, dtype=np.float64)
    a = min(a, n)
    env[:a] = sustain_level * (0.5 - 0.5 * np.cos(np.linspace(0, math.pi, a)))
    r = min(r, n)
    env[n - r:] *= 0.5 + 0.5 * np.cos(np.linspace(0, math.pi, r))
    return env


def one_pole_lowpass(x, cutoff):
    """Gentle 6 dB/octave low-pass, run as an exact recursive filter via cumulative sums."""
    k = math.exp(-2 * math.pi * cutoff / SR)
    y = np.empty_like(x)
    acc = 0.0
    # The loop is fine at these lengths (a few million samples) and keeps numpy-only.
    for i in range(len(x)):
        acc = (1 - k) * x[i] + k * acc
        y[i] = acc
    return y


def pad_voice(note, dur, rng):
    n = int(dur * SR)
    t = np.arange(n) / SR
    f = hz(note)
    out = np.zeros(n)
    for detune in (-5.0, 0.0, 4.0):  # cents
        ff = f * 2 ** (detune / 1200.0)
        ph = rng.uniform(0, 2 * math.pi)
        # Mostly fundamental, a little second and third harmonic: warm, not buzzy.
        out += np.sin(2 * math.pi * ff * t + ph)
        out += 0.18 * np.sin(2 * math.pi * 2 * ff * t + ph * 1.3)
        out += 0.06 * np.sin(2 * math.pi * 3 * ff * t + ph * 0.7)
    breathe = 1.0 + 0.12 * np.sin(2 * math.pi * 0.07 * t + rng.uniform(0, 6.28))
    return out * breathe * envelope(n, attack=2.2, release=2.6) / 3.0


def keys_note(note, dur, vel):
    """An electric-piano-like note: sine with a soft bell partial and an exponential decay."""
    n = int(dur * SR)
    t = np.arange(n) / SR
    f = hz(note)
    tone = np.sin(2 * math.pi * f * t) + 0.22 * np.sin(2 * math.pi * 2 * f * t) * np.exp(-t * 3.0)
    tone += 0.08 * np.sin(2 * math.pi * 3.997 * f * t) * np.exp(-t * 6.0)  # faint tine
    tremolo = 1.0 + 0.05 * np.sin(2 * math.pi * 4.2 * t)
    env = np.exp(-t / 1.6) * envelope(n, attack=0.012, release=0.5)
    return vel * tone * tremolo * env


def bass_note(note, dur):
    n = int(dur * SR)
    t = np.arange(n) / SR
    f = hz(note)
    tone = np.sin(2 * math.pi * f * t) + 0.12 * np.sin(2 * math.pi * 2 * f * t)
    return tone * envelope(n, attack=1.2, release=1.0)


def hall_ir(seconds=2.8, seed=7):
    """A synthetic stereo hall: decorrelated noise with an exponential decay, darkened."""
    rng = np.random.default_rng(seed)
    n = int(seconds * SR)
    t = np.arange(n) / SR
    decay = np.exp(-6.9 * t / seconds)  # -60 dB at `seconds`
    irs = []
    for _ in range(2):
        noise = rng.standard_normal(n) * decay
        noise = one_pole_lowpass(noise, 3800.0)
        noise[: int(0.012 * SR)] = 0  # pre-delay
        irs.append(noise / np.sqrt(np.sum(noise ** 2)))
    return irs


def convolve(x, ir):
    n = len(x) + len(ir) - 1
    size = 1 << (n - 1).bit_length()
    y = np.fft.irfft(np.fft.rfft(x, size) * np.fft.rfft(ir, size), size)[:n]
    return y[: len(x)]


def compose(seconds, seed=20261103):
    rng = np.random.default_rng(seed)
    total = int(seconds * SR)
    left = np.zeros(total + SR * 4)
    right = np.zeros(total + SR * 4)

    def add(sig, start, pan):
        i = int(start * SR)
        j = min(len(left), i + len(sig))
        sig = sig[: j - i]
        gl = math.cos((pan + 1) * math.pi / 4)
        gr = math.sin((pan + 1) * math.pi / 4)
        left[i:j] += sig * gl
        right[i:j] += sig * gr

    chord_len = BARS_PER_CHORD * BAR
    t = 0.0
    k = 0
    while t < seconds:
        chord = PROGRESSION[k % len(PROGRESSION)]
        overlap = 1.6  # chords cross-fade into each other
        for idx, note in enumerate(chord["pad"]):
            pan = -0.45 + 0.9 * idx / (len(chord["pad"]) - 1)
            add(0.055 * pad_voice(note, chord_len + overlap, rng), t, pan)
        # The bass does not cross-fade: two low roots together beat and sound muddy.
        add(0.10 * bass_note(chord["root"], chord_len + 0.2), t, 0.0)
        # Sparse keys: on beats 1 and 3 of the first bar, beat 2 of the second, gently varied.
        hits = [0.0, 2.0, 5.0] if k % 2 == 0 else [0.0, 3.0, 4.5]
        for h_i, beat in enumerate(hits):
            note = chord["keys"][(k + h_i * 2) % len(chord["keys"])]
            vel = 0.09 * (0.8 + 0.4 * rng.random())
            add(keys_note(note, 3.4, vel), t + beat * BEAT, -0.3 if h_i % 2 else 0.3)
        t += chord_len
        k += 1

    dry_l, dry_r = left[:total], right[:total]
    ir_l, ir_r = hall_ir()
    wet_l, wet_r = convolve(dry_l, ir_l), convolve(dry_r, ir_r)
    mix_l = 0.72 * dry_l + 0.55 * wet_l
    mix_r = 0.72 * dry_r + 0.55 * wet_r

    # Gentle overall fades so the bed can start and stop anywhere in the edit.
    fade = envelope(total, attack=3.0, release=4.0)
    mix_l *= fade
    mix_r *= fade

    peak = max(np.max(np.abs(mix_l)), np.max(np.abs(mix_r)))
    gain = 10 ** (-3.0 / 20) / peak  # peak at -3 dBFS; loudness is set in the edit
    return np.stack([mix_l * gain, mix_r * gain], axis=1)


def write_wav(path, stereo):
    pcm = np.clip(stereo * 32767.0, -32768, 32767).astype("<i2")
    with wave.open(path, "wb") as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes(pcm.tobytes())


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--seconds", type=float, default=200.0)
    ap.add_argument("--out", required=True)
    args = ap.parse_args()
    import os
    os.makedirs(os.path.dirname(os.path.abspath(args.out)), exist_ok=True)
    audio = compose(args.seconds)
    write_wav(args.out, audio)
    print(f"{args.out}: {args.seconds:.0f} s, peak -3 dBFS")
