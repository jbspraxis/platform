# Agreement previews

Every page of the firm's first agreement — the project contractor agreement, `JBS/AGR/2026/001` — as HQ ›
Documents renders it (docs/DOCUMENTS.md section 12).

| Files | What they show |
|---|---|
| `agreement-issued-p1.png` … `p7.png` | The agreement as it goes out: signed for JBS Praxis, a pen line for the other party |
| `agreement-signed-p1.png` … `p8.png` | The same agreement countersigned, with the **Signature record** page at the end |

The signatures in them are a specimen scribble, nobody's real one.

Rebuild after a layout change, from the repository root:

```
cd praxis_core
AGREEMENT_PREVIEW_DIR=/tmp/agreement DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 \
  DB_USERNAME=root DB_DATABASE=praxis_test_agreement \
  php vendor/bin/phpunit tests/Feature/Letters/AgreementPreviewTest.php
cd ..
python brand/build/agreement_previews.py /tmp/agreement docs/documents/previews
```
