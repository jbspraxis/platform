<div>
    <style>
        .cyber-textarea { width: 100%; height: 120px; background: rgba(0,0,0,0.5); color: #00E5FF; border: 1px dashed rgba(0, 229, 255, 0.3); padding: 10px; font-size: 11px; font-family: 'Roboto Mono', monospace; margin-top: 10px; border-radius: 2px; resize: vertical; }
        .cyber-textarea:focus { outline: none; border-color: #00E5FF; }
        .btn-action { background: rgba(0, 229, 255, 0.1); border: 1px solid #00E5FF; color: #00E5FF; padding: 8px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; cursor: pointer; transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-action:hover { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .user-card { background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255,255,255,0.1); border-left: 3px solid #00E5FF; padding: 20px; margin-bottom: 25px; border-radius: 4px; box-shadow: inset 0 0 15px rgba(0,0,0,0.3); }
        .sig-container { background-color: #FFF; padding: 20px; margin: 15px 0; overflow-x: auto; border-radius: 2px; }
    </style>

    <div style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
        <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: #FFF; letter-spacing: 1px;">SIGNATURE COMMAND CENTER</h1>
        <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #00E5FF;">> {{ count($personnel) }} ACTIVE PERSONNEL FOUND. GENERATING ARCHITECTURE...</p>
    </div>

    @forelse($personnel as $index => $u)
        @php
            $name = strtoupper(htmlspecialchars($u->first_name . ' ' . $u->last_name));
            $title = strtoupper(htmlspecialchars($u->job_title ?? 'STAFF'));
            $phone = htmlspecialchars($u->phone ?? '+234 816 978 9106'); 
            $email = strtolower(htmlspecialchars($u->company_email));
            
            $logo_url = 'https://www.jbspraxis.com/assets/img/logo_dark.png'; 
            $slogan_url = 'https://www.jbspraxis.com/assets/img/animated_slogan1.gif';
            $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=https://jbspraxis.com/kaizen-hub&color=0A1128&bgcolor=00E5FF&margin=2';
            
            // The HTML Payload
            $signature_html = <<<HTML
<table style="font-family: Arial, Helvetica, sans-serif; width: 620px; max-width: 620px; border-collapse: collapse;" border="0" cellspacing="0" cellpadding="0">
    <tbody>
        <tr>
            <td style="background-color: #0a1128; padding: 25px 25px;">
                <table style="width: 100%; border-collapse: collapse;" border="0" cellspacing="0" cellpadding="0">
                    <tbody>
                        <tr>
                            <td style="vertical-align: middle;" width="150">
                                <a style="text-decoration: none;" href="https://www.jbspraxis.com" rel="noopener"> 
                                    <img style="display: block; border: none; outline: none;" src="{$logo_url}" alt="JBS Praxis" width="130" /> 
                                </a>
                            </td>
                            <td style="vertical-align: middle; text-align: right;">
                                <p style="margin: 0; font-family: 'Trebuchet MS', Arial, sans-serif; font-size: 20px; font-weight: bold; color: #ffffff; letter-spacing: 1px; line-height: 1.1;">{$name}</p>
                                <p style="margin: 6px 0 0 0; font-family: 'Courier New', Courier, monospace; font-size: 11px; color: #00e5ff; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.3;">&gt;_ {$title}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background-color: #00e5ff; padding: 12px 25px;">
                <table style="width: 100%; border-collapse: collapse;" border="0" cellspacing="0" cellpadding="0">
                    <tbody>
                        <tr>
                            <td style="font-family: Arial, sans-serif; font-size: 11px; color: #0a1128; font-weight: bold; text-align: left; vertical-align: middle;">
                                <span style="color: #0a1128; text-decoration: none;">Tel: {$phone}</span> &nbsp;&nbsp;|&nbsp;&nbsp; 
                                <a style="color: #0a1128; text-decoration: none;" href="mailto:{$email}">Email: {$email}</a> &nbsp;&nbsp;|&nbsp;&nbsp; 
                                <a style="color: #0a1128; text-decoration: none;" href="https://www.jbspraxis.com">Website: www.jbspraxis.com</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background-color: #141e3c; padding: 20px 25px;">
                <table style="width: 100%; border-collapse: collapse;" border="0" cellspacing="0" cellpadding="0">
                    <tbody>
                        <tr>
                            <td style="font-family: Arial, sans-serif; text-align: left; vertical-align: top; padding-right: 15px;">
                                <div style="margin: 0 0 10px 0;">
                                    <img style="display: block; border: none;" src="{$slogan_url}" alt="Beyond Strategy..." width="400" />
                                </div>
                                <p style="margin: 0 0 12px 0; font-family: Arial, sans-serif; font-size: 10px; color: #e5e7eb; line-height: 1.5; text-align: justify;"><strong>The Predictive Praxis Framework&trade;:</strong> Bridging the gap between boardroom strategy and server-room execution. We deploy elite architecture to eliminate operational waste. <br /><a style="color: #00e5ff; text-decoration: none; font-weight: bold;" href="https://www.jbspraxis.com/services">[ The Predictive Praxis Framework&trade; ]</a></p>
                                <p style="margin: 0; font-family: Arial, sans-serif; font-size: 10px; color: #9ca3af;"><strong>HQ:</strong> Lagos, NG &nbsp;|&nbsp; <strong>CEMAC Desk:</strong> Douala, CM</p>
                            </td>
                            <td style="vertical-align: top; text-align: right;" width="80">
                                <a style="text-decoration: none;" href="https://jbspraxis.com/kaizen-hub" rel="noopener"> 
                                    <img style="display: block; border: 2px solid #00E5FF; border-radius: 4px;" src="{$qr_url}" alt="Scan to enter Kaizen" width="75" height="75" /> 
                                </a>
                                <p style="margin: 6px 0 0 0; font-family: 'Courier New', Courier, monospace; font-size: 9px; font-weight: bold; color: #00e5ff; text-align: center;">KAIZEN</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
HTML;
        @endphp

        <div class="user-card">
            <h2 style="font-family: 'Montserrat', sans-serif; margin: 0 0 10px 0; font-size: 1rem; color: #FFF;">{{ $name }}</h2>
            
            <div class="sig-container">
                {!! $signature_html !!}
            </div>
            
            <button class="btn-action" onclick="copySig('code-{{ $index }}')">
                <i data-lucide="copy" style="width: 14px; height: 14px;"></i> COPY HTML CODE
            </button>
            <textarea id="code-{{ $index }}" class="cyber-textarea" readonly>{{ $signature_html }}</textarea>
        </div>
    @empty
        <div style="text-align: center; padding: 40px; color: #8A9BB2; font-family: 'Roboto Mono', monospace;">
            > NO ACTIVE PERSONNEL FOUND TO GENERATE SIGNATURES.
        </div>
    @endforelse

    <script>
        // Icons are drawn by the app bundle (resources/js/app.js) on load, on wire:navigate and after every update
        
        function copySig(id) {
            var copyText = document.getElementById(id);
            copyText.select();
            copyText.setSelectionRange(0, 99999); 
            navigator.clipboard.writeText(copyText.value);
            alert("Architecture Copied. Open Roundcube -> Settings -> Identities -> Check 'HTML Signature' -> Click '<>' Source Code button -> Paste -> Save.");
        }
    </script>
</div>