<?php

// The opportunity's SOW tab, the client's signing page and the SOW's PDF (Opportunities PR 5). Same keys as lang/fr/sow.php.
return [
    'empty_title' => 'No statement of work yet',
    'empty_text' => 'The statement of work is drafted here once the client has approved a proposal.',
    'no_proposal' => 'The statement of work is drafted from the proposal the client approved. No proposal has been approved yet.',
    'open_proposals' => 'Open the proposals',
    'closed' => 'This opportunity is closed, so its statement of work can no longer change.',
    'read_only' => 'You can read this draft. Whoever manages the opportunity edits it; the owner or the lead approves it.',
    'heading' => 'Statement of work :ref, version :n',
    'version' => 'Version :n',

    'statuses' => [
        'draft' => 'Draft',
        'approved' => 'Approved, not sent',
        'sent' => 'With the client',
        'changes_requested' => 'Changes asked for',
        'signed' => 'Signed',
        'superseded' => 'Replaced',
    ],

    // The sections a blank statement of work starts with
    'sections' => [
        'purpose' => 'Parties and purpose',
        'scope' => 'Scope',
        'deliverables' => 'Deliverables and acceptance',
        'assumptions' => 'Assumptions and dependencies',
        'timeline' => 'Milestones and timeline',
        'governance' => 'Roles and governance',
        'change_control' => 'Change control',
    ],

    'legal_name' => [
        'missing' => 'JBS Praxis’s legal name isn’t set, so the statement of work can’t name the contracting party. The owner sets it in Website settings › Legal name; it can’t be approved until then.',
    ],

    'start' => [
        'title' => 'Write the statement of work',
        'intro' => 'It starts from proposal :ref, version :n, which :name approved, and becomes the contract you both sign. The fees come from that proposal; you set out the scope, deliverables and the milestones the work is accepted and paid in.',
        'no_transcripts' => 'Only someone who can read the meetings’ transcripts can ask the AI to draft it. You can start a blank one.',
        'instructions_help' => 'Optional: what to stress, what to leave out, how to split the milestones.',
        'transcripts' => 'Also read the full transcripts',
        'transcripts_help' => 'By default the AI reads the meetings’ notes, which costs less. Tick this when the detail matters.',
        'language' => 'The AI writes it in :language, the opportunity’s language.',
        'cost' => 'A draft costs about $0.15 to $0.30 of the AI budget.',
        'ai' => 'Draft with AI',
        'blank' => 'Start a blank one',
    ],

    'bar' => [
        'draft' => 'Draft, last changed on :date',
        'approved' => 'Approved and signed for JBS Praxis by :name on :date, not sent yet',
        'sent' => 'Sent on :date, to be signed by :until',
        'signed' => 'Signed by :name on :date',
        'parties' => 'Between :party and :client',
        'pdf' => 'PDF',
        'preview' => 'Preview the PDF',
    ],

    'drafting' => [
        'title' => 'The AI is drafting version :n',
        'text' => 'It reads the approved proposal, the meetings’ notes and the questionnaire’s answers. This takes a few minutes; you can leave this page and come back.',
        'progress' => 'Drafting progress',
        'failed' => 'The AI couldn’t draft the statement of work: :message',
    ],

    'redraft' => [
        'button' => 'Draft again with AI',
        'title' => 'Draft this version again with the AI',
        'intro' => 'Its draft replaces the title, every section and the milestones.',
        'intro_version' => 'It writes this version again from version :n and what the client asked to change. Its draft replaces the title, every section and the milestones.',
        'submit' => 'Draft again',
    ],

    'editor' => [
        'title' => 'Edit the draft',
        'doc_title' => 'Title',
        'sections' => 'Sections',
        'new_section' => 'New section',
    ],

    'changes' => [
        'label' => 'What changed since version :n',
        'help' => 'The client reads this first, in the email and on the signing page. Required before this version is approved.',
        'asked' => 'What the client asked to change in version :n:',
    ],

    'milestones' => [
        'title' => 'Milestones',
        'intro' => 'The units the work is delivered, accepted and paid in. Each one’s weight is its share of the contract; together they must make 100%. They become the project’s milestones once it is signed.',
        'milestone' => 'Milestone',
        'code' => 'M:n',
        'weeks' => 'Length',
        'weeks_count' => ':count week|:count weeks',
        'weight' => 'Weight',
        'weight_input' => 'Weight (%)',
        'amount' => 'Amount before VAT',
        'deliverables' => 'What it delivers and how it is accepted',
        'none' => 'No milestones yet.',
        'add' => 'Add a milestone',
        'up' => 'Move milestone :n up',
        'down' => 'Move milestone :n down',
        'remove' => 'Remove milestone :n',
        'remove_confirm' => 'Remove this milestone from the draft?',
        'weight_total' => 'Weights: :total of 100%',
        'weight_off' => 'they must add up to 100%',
        'amount_total' => 'Amounts: :amount of :total, before VAT',
    ],

    'fees' => [
        'title' => 'Fees and payment',
        'intro' => 'From the approved proposal :ref, version :n. They can’t change here: a new price needs a new proposal.',
        'deposit' => 'Deposit',
        'deposit_text' => ':percent% on signing: :amount, invoiced at once',
    ],

    'terms' => [
        'validity_input' => 'Days the client has to sign',
        'sign_by' => 'To be signed by',
    ],

    'approve' => [
        'title' => 'Approve and sign for JBS Praxis',
        'intro' => 'Approving signs it for :party: your name and signature appear on the statement of work the client receives. Check every section, the milestones and the fees first.',
        'who' => 'The owner or the opportunity’s lead (:name) approves and signs it for JBS Praxis.',
        'name' => 'Your name, as it appears on the contract',
        'signature' => 'Your signature',
        'signature_help' => 'Draw it with your mouse, finger or pen.',
        'button' => 'Approve and sign',
        'done' => 'Approved and signed for JBS Praxis. Send it to the client below.',
        'reopen' => 'Take back to draft',
        'reopen_confirm' => 'Take it back to draft? Your signature is removed and you sign again after the changes.',
        'reopened' => 'Back to draft; the JBS Praxis signature was removed.',
        'errors' => [
            'name' => 'Enter your name as it appears on the contract.',
            'signature' => 'Draw your signature in the box.',
            'signed' => 'The client already signed a version, so no other version can be approved.',
            'state' => 'Only a draft nobody has approved can be approved.',
            'running' => 'The AI is still writing this version. Approve it once the draft is in.',
            'empty' => 'Give it a title and write at least one section first.',
            'milestones' => 'The milestones’ weights must add up to exactly 100%.',
            'changes' => 'Say what changed since the previous version first.',
            'legal_name' => 'JBS Praxis’s legal name isn’t set. Ask the owner to set it in Website settings › Legal name.',
            'proposal' => 'The proposal it is drafted from is no longer the one the client approved.',
        ],
    ],

    'send' => [
        'title' => 'Send it to the client',
        'intro' => 'They receive a branded email in :language with the PDF and a link to sign it online with a one-time code.',
        'to_help' => 'Only someone from the client’s organisation with an active contact record can sign it; others can read it.',
        'submit' => 'Send the statement of work',
        'done' => 'The statement of work is on its way.',
        'link_intro' => 'The same email with the link and the PDF. The link works until :date.',
        'link_done' => 'The link is on its way again.',
        'errors' => [
            'state' => 'Only an approved version that hasn’t been sent can be sent. Reload the tab.',
            'link_state' => 'Only a version that is with the client and still valid can be sent again.',
        ],
    ],

    'client' => [
        'waiting' => 'Waiting for the client to sign it.',
        'expired' => 'The time to sign it ended on :date. Start a new version to send it again.',
        'signed' => ':name (:title, :email) signed it on :date.',
        'deposit' => 'Deposit invoice :number (:amount) was raised.',
        'open_project' => 'Open the project draft',
        'changes' => ':who asked for changes on :date.',
        'superseded' => 'A later version replaced this one.',
        'set_aside' => 'Set aside: the client signed version :n.',
    ],

    'versions' => [
        'label' => 'Version',
        'new' => 'Start version :n',
        'new_help' => 'Copies the version the client has, for you to change.',
        'started' => 'Version :n started.',
        'discard' => 'Discard this draft',
        'discard_confirm' => 'Discard the draft of version :n? It hasn’t reached the client.',
        'discarded' => 'The draft of version :n was discarded.',
        'not_discarded' => 'This version can’t be discarded now.',
    ],

    'errors' => [
        'not_editable' => 'This version can no longer be changed.',
        'not_saved' => 'Your last change wasn’t saved: this version changed meanwhile. Here it is as it stands now.',
        'note_long' => 'Keep the instructions under :max characters.',
        'no_proposal' => 'The client hasn’t approved a proposal yet.',
    ],

    'signature' => [
        'clear' => 'Clear',
    ],

    // A SOW brought in by opportunities:import (signed on paper before the pipeline, e.g. MyCity)
    'imported' => [
        'signed' => 'Signed on paper on :date and imported into HQ.',
        'by' => 'For the client: :name.',
        'where' => 'Its fees and payment terms are in the signed document and the project’s invoices; HQ makes no PDF of it.',
        'on_paper' => 'Signed on paper on :date',
    ],

    'signatures' => [
        'title' => 'Signatures',
        'for' => 'For :party',
        'on' => 'Signed on :date',
        'waiting' => 'Not signed yet.',
        'waiting_you' => 'Waiting for your signature.',
        'image' => 'Signature of :name',
    ],

    'notify' => [
        'ready_title' => 'Statement of work drafted: :title',
        'ready' => 'Version :n is ready for you to review and edit.',
        'failed_title' => 'Statement of work not drafted: :title',
        'failed' => 'The AI couldn’t draft it: :message',
        'changes_title' => 'Changes asked for on the statement of work',
        'changes' => ':name asked for changes to version :version of the statement of work for “:title”.',
        'signed_title' => 'Statement of work signed: :title',
        'signed' => ':name of :company signed version :version. Deposit invoice :invoice is raised; the project draft is being prepared.',
    ],

    'pdf' => [
        'kicker' => 'Statement of work',
        'footer' => 'JBS Praxis · Statement of work :ref, version :version · Confidential',
        'between' => 'Between',
        'and' => 'And',
        'reference' => 'Reference',
        'proposal' => 'Approved proposal',
        'date' => 'Date',
        'valid_until' => 'To be signed by',
        'changes' => 'What changed since version :n',
        'milestones' => 'Milestones',
        'fees' => 'Fees and payment',
        'signatures' => 'Signatures',
        'for' => 'For :party',
        'signed_on' => 'Signed electronically on :date',
        'not_signed' => 'Not signed yet',
        'sign_online' => 'To be signed online with a one-time code',
    ],

    // The client's page in the portal
    'portal' => [
        'kicker' => 'Statement of work',
        'page_title' => 'Statement of work: :title',
        'subtitle' => 'Between :party and :company.',
        'sent' => 'Sent on :date',
        'sign_by' => 'To be signed by :date',
        'sign' => 'Sign it',
        'sign_help' => 'With your name, title and signature, then a code we email you.',
        'signers_only' => 'Signing is for the people who approve for :company in the Client Portal. You can ask for changes; they sign from their own email or the portal.',
        'sign_intro' => 'Sign for :company: use the email address we know you by. We email you a 6-digit code to confirm it is you.',
        'signature' => 'Your signature',
        'signature_help' => 'Draw it with your mouse, finger or pen.',
        'send_code' => 'Email me the code',
        'code_intro' => 'We emailed a 6-digit code to :email. It works for 10 minutes.',
        'code_intro_reload' => 'We emailed a 6-digit code to the signer’s address. It works for 10 minutes. Signing for someone else? Ask for a new code with your own details.',
        'signing_as' => 'You are signing as :name, :title.',
        'sign_submit' => 'Sign the statement of work',
        'new_code' => 'Ask for a new code',
        'changes' => 'Ask for changes',
        'changes_help' => 'Tell us what to change; we send you a new version.',
        'changes_intro' => 'What should change? We read it and send you a new version to sign.',
        'message' => 'What to change',
        'pdf_help' => 'The same statement of work, to save or share inside your organisation.',
        'done_signed' => 'Thank you: the statement of work is signed. The deposit invoice is in your client portal, and we email you to set up your access if you don’t have it yet.',
        'done_changes' => 'Thank you: we will come back to you with a new version.',
        'signed' => ':name signed this statement of work on :date.',
        'changes_requested' => 'Changes were asked for on this version. We will send you a new one.',
        'superseded' => 'A newer version of this statement of work was sent to you.',
        'expired' => 'The time to sign this version ended on :date. Reply to our email and we will send you an up-to-date one.',
        'errors' => [
            'not_contact' => 'Only someone from :company with an active contact record with us can sign it. Ask your primary contact, or reply to our email.',
            'not_approver' => 'Only the people who approve for :company can sign it. You can ask for changes instead.',
            'signature' => 'Draw your signature in the box.',
            'note' => 'Tell us what to change.',
            'too_many_codes' => 'We’ve sent 3 signing codes for this statement of work in the last 15 minutes. Wait :wait, then ask for a new code.',
            'too_many_codes_email' => 'We’ve sent 3 signing codes to this email address in the last 15 minutes. Wait :wait, then ask for a new code.',
            'too_many_attempts' => 'Too many wrong codes, so this code no longer works. Wait :wait, then ask for a new code.',
            'code_format' => 'Enter the 6-digit code from the email.',
            'code_invalid' => 'That code isn’t right or has expired. Check the latest email, or ask for a new code.',
            'email_failed' => 'We couldn’t email your signing code just now. Try again in a few minutes.',
            'closed' => 'This version can no longer be signed or changed. Reload the page to see where it stands.',
            'expired' => 'The time to sign this version has ended.',
            'link_expired' => 'This link has expired. Reply to our email and we will send you a new one.',
        ],
    ],
];
