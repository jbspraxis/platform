# Voices: models, licences and credits

Decided by the owner on 30 October 2026: open-source voices instead of Microsoft Azure (Azure's
free trial refused the account and the pay-as-you-go sign-up failed at the card step). Policy:
**credits only**, meaning the credit lines the licences require, and no further note about how
the narration was made.

## English narration: YarnGPT2b, voice "idera"

| Part | Source | Licence |
|---|---|---|
| YarnGPT2b, the Nigerian-accented English model (revision `eabc85c6`) | https://huggingface.co/saheedniyi/YarnGPT2b | Apache-2.0 (commercial use allowed, no attribution required for the audio) |
| WavTokenizer large speech 75-token decoder, `wavtokenizer_large_speech_320_24k.ckpt` (revision `1cc9faee`, sha256 `7450020c…cabcc5a`) | https://huggingface.co/novateur/WavTokenizer-large-speech-75token | MIT |
| `yarngpt` helper package 0.2.0 (prompting code and the default speaker prompts) | https://pypi.org/project/yarngpt/ | MIT |
| WavTokenizer decoder code as packaged in `outetts` 0.3.3 (only the `wav_tokenizer` folder is used) | https://pypi.org/project/outetts/0.3.3/ | Apache-2.0 (OuteTTS); the WavTokenizer code inside is MIT |

Note for the record: YarnGPT2b's model card says it was trained on publicly available Nigerian
films and podcasts and on open Nigerian audio datasets. The model is licensed Apache-2.0 by its
author. If anyone ever says the narration sounds like them, swap the English voice in
`pipeline/voice/voices.json`, regenerate the English lines and re-run the edit.

## French narration (temporary): Kokoro-82M, voice "ff_siwis"

| Part | Source | Licence |
|---|---|---|
| Kokoro-82M model (`kokoro-v1_0.pth`) and the `kokoro` package | https://huggingface.co/hexgrad/Kokoro-82M | Apache-2.0 |
| Voice `ff_siwis`, trained on the SIWIS French speech synthesis database | https://datashare.is.ed.ac.uk/handle/10283/2353 | **CC BY 4.0: credit required** |

**The French voice is temporary.** It has a France accent; the runbook's plan is to replace it
with a West or Central African French voice when one is available. Swapping it is one setting in
`pipeline/voice/voices.json`, then regenerating the French lines and re-running the edit.

## Credit line (required, used in the YouTube descriptions of the French videos)

> French voice: Kokoro-82M (ff_siwis), trained on the SIWIS French speech synthesis database (CC BY 4.0).
