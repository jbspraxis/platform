<#
.SYNOPSIS
    Makes the JBS Praxis backup key on this PC (docs/BACKUPS.md, section 3).

.DESCRIPTION
    Creates a 4096-bit RSA key pair in "Documents\JBS Praxis backup key":
      backup-private-key.pem   opens every server backup. Stays on this PC and in your password manager. Never send it.
      backup-public-key.pem    goes to the server (resources/backups/owner-public-key.pem). Safe to share.
    It also copies the restore tool next to the key, so the key folder is all you need on the day of a restore.
    The private key has no passphrase (the owner's choice, 4 Nov 2026): keep the file safe.
    Refuses to overwrite a key that already exists: backups made for it would become unreadable.
    Needs nothing installed: Windows PowerShell 5.1 and .NET, as on every Windows 10 or 11 PC.

.EXAMPLE
    Double-click "Make backup key.cmd", or:
    powershell -NoProfile -ExecutionPolicy Bypass -File .\New-BackupKey.ps1
#>
[CmdletBinding()]
param(
    [string]$OutDir = (Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'JBS Praxis backup key'),
    [ValidateSet(3072, 4096)][int]$Bits = 4096
)
$ErrorActionPreference = 'Stop'

Add-Type -TypeDefinition @'
using System;
using System.Security.Cryptography;
using System.Text;

public static class JbsBackupKey
{
    // Returns the private key (PKCS#1 PEM), the public key (SubjectPublicKeyInfo PEM) and the fingerprint
    // (SHA-256 of the public key's DER, as openssl and HQ compute it)
    public static string[] Make(int bits)
    {
        using (RSACryptoServiceProvider rsa = new RSACryptoServiceProvider(bits))
        {
            rsa.PersistKeyInCsp = false;
            RSAParameters p = rsa.ExportParameters(true);
            byte[] priv = Seq(Int(new byte[] { 0 }), Int(p.Modulus), Int(p.Exponent), Int(p.D), Int(p.P), Int(p.Q), Int(p.DP), Int(p.DQ), Int(p.InverseQ));
            byte[] spki = Spki(p);
            string fp;
            using (SHA256 sha = SHA256.Create()) { fp = Hex(sha.ComputeHash(spki)); }
            string[] result = new string[] { Pem("RSA PRIVATE KEY", priv), Pem("PUBLIC KEY", spki), fp };
            Array.Clear(priv, 0, priv.Length);
            return result;
        }
    }

    public static byte[] Spki(RSAParameters p)
    {
        byte[] rsaEncryption = new byte[] { 0x06, 0x09, 0x2a, 0x86, 0x48, 0x86, 0xf7, 0x0d, 0x01, 0x01, 0x01, 0x05, 0x00 };
        byte[] key = Seq(Int(p.Modulus), Int(p.Exponent));
        byte[] bits = new byte[key.Length + 1];
        Array.Copy(key, 0, bits, 1, key.Length);
        return Seq(Tlv(0x30, rsaEncryption), Tlv(0x03, bits));
    }

    static byte[] Int(byte[] value)
    {
        int i = 0;
        while (i < value.Length - 1 && value[i] == 0) i++;
        int pad = (value[i] & 0x80) != 0 ? 1 : 0;
        byte[] v = new byte[value.Length - i + pad];
        Array.Copy(value, i, v, pad, value.Length - i);
        return Tlv(0x02, v);
    }

    static byte[] Seq(params byte[][] parts)
    {
        int length = 0;
        foreach (byte[] part in parts) length += part.Length;
        byte[] all = new byte[length];
        int at = 0;
        foreach (byte[] part in parts) { Array.Copy(part, 0, all, at, part.Length); at += part.Length; }
        return Tlv(0x30, all);
    }

    static byte[] Tlv(byte tag, byte[] value)
    {
        byte[] len;
        if (value.Length < 0x80) len = new byte[] { (byte)value.Length };
        else if (value.Length <= 0xFF) len = new byte[] { 0x81, (byte)value.Length };
        else if (value.Length <= 0xFFFF) len = new byte[] { 0x82, (byte)(value.Length >> 8), (byte)value.Length };
        else len = new byte[] { 0x83, (byte)(value.Length >> 16), (byte)(value.Length >> 8), (byte)value.Length };
        byte[] tlv = new byte[1 + len.Length + value.Length];
        tlv[0] = tag;
        Array.Copy(len, 0, tlv, 1, len.Length);
        Array.Copy(value, 0, tlv, 1 + len.Length, value.Length);
        return tlv;
    }

    static string Pem(string label, byte[] der)
    {
        string b64 = Convert.ToBase64String(der);
        StringBuilder sb = new StringBuilder("-----BEGIN " + label + "-----\n");
        for (int i = 0; i < b64.Length; i += 64) sb.Append(b64.Substring(i, Math.Min(64, b64.Length - i))).Append('\n');
        return sb.Append("-----END " + label + "-----\n").ToString();
    }

    public static string Hex(byte[] bytes)
    {
        StringBuilder sb = new StringBuilder();
        foreach (byte b in bytes) sb.Append(b.ToString("x2"));
        return sb.ToString();
    }
}
'@

$private = Join-Path $OutDir 'backup-private-key.pem'
$public = Join-Path $OutDir 'backup-public-key.pem'
if (Test-Path -LiteralPath $private) {
    Write-Host ''
    Write-Host "A backup key already exists in $OutDir." -ForegroundColor Yellow
    Write-Host 'Nothing was changed. A new key could not open the backups made with this one.'
    Write-Host 'To replace it on purpose, first move that folder somewhere safe (keep the old key for the old backups).'
    exit 1
}

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
Write-Host "Making a $Bits-bit key (a few seconds)..."
$made = [JbsBackupKey]::Make($Bits)
$ascii = New-Object System.Text.ASCIIEncoding
[System.IO.File]::WriteAllText($private, $made[0], $ascii)
[System.IO.File]::WriteAllText($public, $made[1], $ascii)
$made[0] = $null

# Only this Windows account may read the private key
try {
    $acl = New-Object System.Security.AccessControl.FileSecurity
    $acl.SetAccessRuleProtection($true, $false)
    $me = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
    $acl.AddAccessRule((New-Object System.Security.AccessControl.FileSystemAccessRule($me, 'FullControl', 'Allow')))
    Set-Acl -LiteralPath $private -AclObject $acl
} catch {
    Write-Host "Could not limit who may read the private key ($($_.Exception.Message)); it is still in place." -ForegroundColor Yellow
}

$fingerprint = (($made[2].Substring(0, 32)) -split '(.{4})' | Where-Object { $_ }) -join ' '
foreach ($tool in 'Restore-Backup.ps1', 'Restore a backup.cmd') {
    $source = Join-Path $PSScriptRoot $tool
    if (Test-Path -LiteralPath $source) { Copy-Item -LiteralPath $source -Destination $OutDir -Force }
}
$readme = @"
JBS Praxis backup key, made $(Get-Date -Format 'd MMMM yyyy, HH:mm') on $env:COMPUTERNAME
Fingerprint: $fingerprint

backup-private-key.pem   Opens every server backup. Keep it on this PC and in your password manager
                         (as an attached file or a secure note with its full text). Never send it to anyone,
                         never put it on the server, in an email, a chat or Google Drive.
backup-public-key.pem    The server encrypts with it. Safe to share: it goes into the repository as
                         praxis_core/resources/backups/owner-public-key.pem.
Restore a backup.cmd     Double-click it to open a backup downloaded from Drive (docs/BACKUPS.md, section 6).

If this PC is lost, the copy in your password manager is the only way to open the backups.
"@
[System.IO.File]::WriteAllText((Join-Path $OutDir 'READ ME - backup key.txt'), $readme, (New-Object System.Text.UTF8Encoding $false))

Write-Host ''
Write-Host 'Done. Your backup key is in:' -ForegroundColor Green
Write-Host "  $OutDir"
Write-Host "  Fingerprint: $fingerprint"
Write-Host ''
Write-Host 'Next:'
Write-Host '  1. Save backup-private-key.pem in your password manager now (attach the file, or paste its whole text).'
Write-Host '  2. Tell the lead chat the key is made: it puts backup-public-key.pem (only that one) on the server.'
Write-Host '  3. Never send backup-private-key.pem to anyone, Claude included.'
