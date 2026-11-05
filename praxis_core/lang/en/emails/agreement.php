<?php

// AgreementMail: an agreement sent for signature, and the signed copy that follows (docs/DOCUMENTS.md 12).
// The one-time code is never in these emails; it is asked for on the signing page and sent on its own.
return [
    'request_subject' => 'Please sign: :title',
    'request_headline' => 'An agreement to sign',
    'request_preheader' => 'Read :title and sign it online.',
    'request_intro' => 'JBS Praxis has prepared and signed :title. Please read it and, if you are happy with it, sign it online.',
    'request_how' => 'Open the link below, read the whole agreement, then type your full name and sign. We will email you a six-digit code to confirm it is you.',
    'request_button' => 'Read and sign the agreement',
    'request_expires' => 'This link is yours alone and stops working after :days days.',
    'request_expires_on' => 'This link is yours alone and stops working on :date.',
    'request_private' => 'If you need a new one, just ask us.',
    'request_pdf' => 'The agreement is attached as a PDF so you can keep or print a copy before you sign.',
    'request_reason' => 'You are receiving this because JBS Praxis has sent you an agreement to sign.',

    'signed_subject' => 'Signed: :title',
    'signed_headline' => 'The agreement is signed',
    'signed_preheader' => ':title has been signed by both parties.',
    'signed_intro' => ':title has now been signed by every party. The signed copy is attached.',
    'signed_record' => 'The last page of the signed copy records who signed, when, and from where, together with the fingerprint of the agreement as it was issued. The issued document itself is unchanged.',
    'signed_pdf' => 'Keep the attached PDF: it is the signed version, and the one to rely on.',
    'signed_reason' => 'You are receiving this because you are a party to this agreement with JBS Praxis.',

    'fact_reference' => 'Reference',
    'fact_date' => 'Dated',
    'fact_parties' => 'Between',
    'fact_signed_on' => 'Signed on',
];
