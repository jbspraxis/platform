<#
.SYNOPSIS
    Opens a JBS Praxis server backup downloaded from Drive (docs/BACKUPS.md, section 6).

.DESCRIPTION
    Checks the file is intact and unchanged (its HMAC tag) before trusting a byte, decrypts it with the private key,
    and checks the result against the checksum recorded when it was made. Then:
      a database backup (..._db_....sql.gz.enc)   writes the .sql.gz and the .sql (DEFINER clauses removed, so it imports
                                              under any MySQL user), and can import it into this PC's MySQL;
      a files backup (..._files_....tar.gz.enc)   writes the .tar.gz, and can unpack it.
    Needs nothing installed: Windows PowerShell 5.1 and .NET, as on every Windows 10 or 11 PC. Importing needs MySQL
    (Laragon's is found by itself). The private key never leaves this PC.

.EXAMPLE
    Double-click "Restore a backup.cmd" and choose the file.

.EXAMPLE
    .\Restore-Backup.ps1 -File .\praxis_db_2026-11-05.sql.gz.enc -Import -Database praxis_restored
#>
[CmdletBinding()]
param(
    [string]$File,
    [string]$Key = (Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'JBS Praxis backup key\backup-private-key.pem'),
    [string]$OutDir,
    [switch]$Import,
    [string]$Database,
    [string]$MySql,
    [string]$MySqlHost = '127.0.0.1',
    [int]$MySqlPort = 3306,
    [string]$MySqlUser = 'root',
    [switch]$AskMySqlPassword,
    [switch]$Extract,
    [switch]$KeepDefiners,
    [switch]$NoPrompt
)
$ErrorActionPreference = 'Stop'

Add-Type -TypeDefinition @'
using System;
using System.IO;
using System.IO.Compression;
using System.Security.Cryptography;
using System.Text;
using System.Text.RegularExpressions;

public static class JbsBackup
{
    public const string Magic = "JBSPRAXIS-BACKUP-1\n";

    public class Envelope
    {
        public string Json;
        public byte[] Locked;
        public byte[] Counter;
        public long DataStart;
        public long DataLength;
    }

    public static Envelope Read(string path)
    {
        using (FileStream f = File.OpenRead(path))
        {
            byte[] magic = Exactly(f, Magic.Length);
            if (Encoding.ASCII.GetString(magic) != Magic) throw new Exception("This is not a JBS Praxis server backup.");
            byte[] n = Exactly(f, 4);
            int headerLength = (n[0] << 24) | (n[1] << 16) | (n[2] << 8) | n[3];
            if (headerLength <= 0 || headerLength > 65535) throw new Exception("The file's header is damaged.");
            Envelope e = new Envelope();
            e.Json = Encoding.UTF8.GetString(Exactly(f, headerLength));
            byte[] w = Exactly(f, 2);
            e.Locked = Exactly(f, (w[0] << 8) | w[1]);
            e.Counter = Exactly(f, 16);
            e.DataStart = f.Position;
            e.DataLength = f.Length - f.Position - 32;
            if (e.DataLength < 0) throw new Exception("The file is cut short.");
            return e;
        }
    }

    // Decrypts after checking the tag; writes outPath only when everything matches. Returns the plain file's SHA-256.
    public static string Open(string path, string outPath, string pem)
    {
        Envelope e = Read(path);
        byte[] keys;
        using (RSACryptoServiceProvider rsa = new RSACryptoServiceProvider())
        {
            rsa.PersistKeyInCsp = false;
            rsa.ImportParameters(PrivateKey(pem));
            try { keys = rsa.Decrypt(e.Locked, true); }
            catch (CryptographicException) { throw new Exception("This private key does not open this backup."); }
        }
        if (keys.Length != 64) throw new Exception("This private key does not open this backup.");
        byte[] aesKey = new byte[32], macKey = new byte[32];
        Array.Copy(keys, 0, aesKey, 0, 32);
        Array.Copy(keys, 32, macKey, 0, 32);

        byte[] buffer = new byte[1 << 20];
        using (FileStream f = File.OpenRead(path))
        using (HMACSHA256 mac = new HMACSHA256(macKey))
        {
            // 1. The tag, over every byte before it
            long left = e.DataStart + e.DataLength;
            while (left > 0)
            {
                int read = f.Read(buffer, 0, (int)Math.Min(buffer.Length, left));
                if (read <= 0) throw new Exception("The file is cut short.");
                mac.TransformBlock(buffer, 0, read, null, 0);
                left -= read;
            }
            mac.TransformFinalBlock(new byte[0], 0, 0);
            byte[] tag = Exactly(f, 32);
            int diff = 0;
            for (int i = 0; i < 32; i++) diff |= tag[i] ^ mac.Hash[i];
            if (diff != 0) throw new Exception("The file was changed or damaged after it was made (its tag does not match). Download it again.");

            // 2. AES-256-CTR, counting up from the stored counter as one 128-bit big-endian number
            f.Position = e.DataStart;
            string tmp = outPath + ".part";
            using (Aes aes = Aes.Create())
            using (SHA256 sha = SHA256.Create())
            {
                aes.Mode = CipherMode.ECB;
                aes.Padding = PaddingMode.None;
                aes.Key = aesKey;
                using (ICryptoTransform block = aes.CreateEncryptor())
                using (FileStream o = File.Create(tmp))
                {
                    byte[] counter = (byte[])e.Counter.Clone();
                    byte[] blocks = new byte[buffer.Length];
                    byte[] stream = new byte[buffer.Length];
                    left = e.DataLength;
                    while (left > 0)
                    {
                        int read = f.Read(buffer, 0, (int)Math.Min(buffer.Length, left));
                        if (read <= 0) throw new Exception("The file is cut short.");
                        int whole = (read + 15) / 16 * 16;
                        for (int at = 0; at < whole; at += 16) { Array.Copy(counter, 0, blocks, at, 16); Increment(counter); }
                        block.TransformBlock(blocks, 0, whole, stream, 0);
                        for (int i = 0; i < read; i++) buffer[i] ^= stream[i];
                        o.Write(buffer, 0, read);
                        sha.TransformBlock(buffer, 0, read, null, 0);
                        left -= read;
                    }
                    sha.TransformFinalBlock(new byte[0], 0, 0);
                }
                Array.Clear(aesKey, 0, 32);
                Array.Clear(keys, 0, keys.Length);
                if (File.Exists(outPath)) File.Delete(outPath);
                File.Move(tmp, outPath);
                return Hex(sha.Hash);
            }
        }
    }

    // A .sql.gz to .sql. DEFINER=`user`@`host` is removed outside INSERT lines unless keep is set. Works on bytes
    // (Latin-1 maps each byte to one character), so binary data in the dump comes through unchanged.
    public static long Gunzip(string gz, string sql, bool keepDefiners)
    {
        Encoding latin1 = Encoding.GetEncoding(28591);
        Regex definer = new Regex(@"\bDEFINER\s*=\s*(?:`(?:[^`]|``)*`|'[^']*'|[^\s@]+)@(?:`(?:[^`]|``)*`|'[^']*'|[^\s*]+)");
        long lines = 0;
        using (FileStream input = File.OpenRead(gz))
        using (GZipStream unzip = new GZipStream(input, CompressionMode.Decompress))
        using (BufferedStream reader = new BufferedStream(unzip, 1 << 20))
        using (FileStream output = File.Create(sql))
        using (BufferedStream writer = new BufferedStream(output, 1 << 20))
        {
            MemoryStream line = new MemoryStream();
            int b;
            while (true)
            {
                b = reader.ReadByte();
                if (b >= 0) line.WriteByte((byte)b);
                if (b == '\n' || (b < 0 && line.Length > 0))
                {
                    byte[] bytes = line.ToArray();
                    if (!keepDefiners && !StartsWith(bytes, "INSERT INTO ") && Contains(bytes, "DEFINER"))
                    {
                        bytes = latin1.GetBytes(definer.Replace(latin1.GetString(bytes), ""));
                    }
                    writer.Write(bytes, 0, bytes.Length);
                    line.SetLength(0);
                    lines++;
                }
                if (b < 0) break;
            }
        }
        return lines;
    }

    public static string Fingerprint(string pem)
    {
        RSAParameters p = PrivateKey(pem);
        byte[] rsaEncryption = new byte[] { 0x06, 0x09, 0x2a, 0x86, 0x48, 0x86, 0xf7, 0x0d, 0x01, 0x01, 0x01, 0x05, 0x00 };
        byte[] key = Seq(Int(p.Modulus), Int(p.Exponent));
        byte[] bits = new byte[key.Length + 1];
        Array.Copy(key, 0, bits, 1, key.Length);
        using (SHA256 sha = SHA256.Create()) { return Hex(sha.ComputeHash(Seq(Tlv(0x30, rsaEncryption), Tlv(0x03, bits)))); }
    }

    public static string Sha256File(string path)
    {
        using (FileStream f = File.OpenRead(path))
        using (SHA256 sha = SHA256.Create()) { return Hex(sha.ComputeHash(f)); }
    }

    // PKCS#1 ("BEGIN RSA PRIVATE KEY") or PKCS#8 ("BEGIN PRIVATE KEY"), unencrypted
    static RSAParameters PrivateKey(string pem)
    {
        if (pem.Contains("ENCRYPTED")) throw new Exception("This private key has a passphrase: remove it first with openssl, or ask for help.");
        Match m = Regex.Match(pem, "-----BEGIN (RSA )?PRIVATE KEY-----(.*?)-----END", RegexOptions.Singleline);
        if (!m.Success) throw new Exception("This is not the backup private key (a file starting with -----BEGIN RSA PRIVATE KEY-----).");
        byte[] der = Convert.FromBase64String(Regex.Replace(m.Groups[2].Value, @"\s+", ""));
        Der d = new Der(der);
        if (m.Groups[1].Value == "")
        {
            // PKCS#8: SEQUENCE { INTEGER 0, SEQUENCE { rsaEncryption, NULL }, OCTET STRING { PKCS#1 } }
            d.Enter(0x30); d.Next(0x02); d.Next(0x30);
            d = new Der(d.Next(0x04));
        }
        d.Enter(0x30);
        d.Next(0x02);
        RSAParameters p = new RSAParameters();
        p.Modulus = Unsigned(d.Next(0x02), 0);
        p.Exponent = Unsigned(d.Next(0x02), 0);
        int size = p.Modulus.Length;
        p.D = Unsigned(d.Next(0x02), size);
        p.P = Unsigned(d.Next(0x02), size / 2);
        p.Q = Unsigned(d.Next(0x02), size / 2);
        p.DP = Unsigned(d.Next(0x02), size / 2);
        p.DQ = Unsigned(d.Next(0x02), size / 2);
        p.InverseQ = Unsigned(d.Next(0x02), size / 2);
        return p;
    }

    class Der
    {
        byte[] b; int i;
        public Der(byte[] bytes) { b = bytes; i = 0; }
        int Length()
        {
            int first = b[i++];
            if (first < 0x80) return first;
            int n = first & 0x7F, len = 0;
            for (int k = 0; k < n; k++) len = (len << 8) | b[i++];
            return len;
        }
        public void Enter(byte tag) { if (b[i++] != tag) throw new Exception("The private key file is damaged."); Length(); }
        public byte[] Next(byte tag)
        {
            if (b[i++] != tag) throw new Exception("The private key file is damaged.");
            int len = Length();
            byte[] v = new byte[len];
            Array.Copy(b, i, v, 0, len);
            i += len;
            return v;
        }
    }

    // A DER integer as the unsigned big-endian bytes .NET wants, left-padded to size (0 = as short as possible)
    static byte[] Unsigned(byte[] v, int size)
    {
        int start = 0;
        while (start < v.Length - 1 && v[start] == 0) start++;
        int length = v.Length - start;
        byte[] r = new byte[Math.Max(size, length)];
        Array.Copy(v, start, r, r.Length - length, length);
        return r;
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

    static void Increment(byte[] counter)
    {
        for (int i = 15; i >= 0; i--) { if (++counter[i] != 0) break; }
    }

    static byte[] Exactly(Stream s, int n)
    {
        byte[] r = new byte[n];
        int got = 0;
        while (got < n)
        {
            int read = s.Read(r, got, n - got);
            if (read <= 0) throw new Exception("The file is cut short.");
            got += read;
        }
        return r;
    }

    static bool StartsWith(byte[] b, string s)
    {
        if (b.Length < s.Length) return false;
        for (int i = 0; i < s.Length; i++) if (b[i] != s[i]) return false;
        return true;
    }

    static bool Contains(byte[] b, string s)
    {
        for (int i = 0; i + s.Length <= b.Length; i++)
        {
            int k = 0;
            while (k < s.Length && b[i + k] == s[k]) k++;
            if (k == s.Length) return true;
        }
        return false;
    }

    static string Hex(byte[] bytes)
    {
        StringBuilder sb = new StringBuilder();
        foreach (byte x in bytes) sb.Append(x.ToString("x2"));
        return sb.ToString();
    }
}
'@

function Ask([string]$question, [bool]$default) {
    if ($NoPrompt) { return $default }
    $answer = Read-Host "$question $(if ($default) { '[Y/n]' } else { '[y/N]' })"
    if ([string]::IsNullOrWhiteSpace($answer)) { return $default }
    return $answer.Trim().ToLower().StartsWith('y')
}

function Find-MySql {
    if ($MySql) { return $MySql }
    $laragon = Get-ChildItem -Path 'C:\laragon\bin\mysql\*\bin\mysql.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($laragon) { return $laragon.FullName }
    $onPath = Get-Command mysql.exe -ErrorAction SilentlyContinue
    if ($onPath) { return $onPath.Source }
    throw 'MySQL was not found on this PC. Install Laragon (laragon.org), or give its mysql.exe with -MySql.'
}

function Invoke-MySql([string[]]$arguments, [string]$inputFile) {
    $exe = Find-MySql
    $quoted = $arguments | ForEach-Object { if ($_ -match '\s') { '"' + $_ + '"' } else { $_ } }
    $options = @{ FilePath = $exe; ArgumentList = $quoted; NoNewWindow = $true; Wait = $true; PassThru = $true }
    if ($inputFile) { $options.RedirectStandardInput = $inputFile }
    $process = Start-Process @options
    if ($process.ExitCode -ne 0) { throw "mysql stopped with code $($process.ExitCode) (its message is above)." }
}

try {
    if (-not $File) {
        Add-Type -AssemblyName System.Windows.Forms
        $dialog = New-Object System.Windows.Forms.OpenFileDialog
        $dialog.Title = 'Choose a JBS Praxis backup downloaded from Drive'
        $dialog.Filter = 'Server backups (*.enc)|*.enc|All files (*.*)|*.*'
        $dialog.InitialDirectory = [Environment]::GetFolderPath('UserProfile') + '\Downloads'
        if ($dialog.ShowDialog() -ne 'OK') { Write-Host 'No file chosen.'; exit 1 }
        $File = $dialog.FileName
    }
    $File = (Resolve-Path -LiteralPath $File).Path
    if (-not (Test-Path -LiteralPath $Key)) {
        throw "The private key was not found at $Key. Put backup-private-key.pem there (from your password manager), or give its place with -Key."
    }
    if (-not $OutDir) { $OutDir = Split-Path -Parent $File }
    New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

    $envelope = [JbsBackup]::Read($File)
    $header = $envelope.Json | ConvertFrom-Json
    $pem = [System.IO.File]::ReadAllText($Key)
    $mine = [JbsBackup]::Fingerprint($pem)
    Write-Host ''
    Write-Host "Backup:  $($header.site), $($header.kind) of $($header.date) ($([math]::Round($header.size / 1MB, 1)) MB once opened)"
    Write-Host "Key:     $(($mine.Substring(0, 32) -split '(.{4})' | Where-Object { $_ }) -join ' ')"
    if ($header.key -and $header.key -ne $mine) {
        throw "This backup was made for another key (fingerprint $(($header.key.Substring(0, 32) -split '(.{4})' | Where-Object { $_ }) -join ' ')). Use the key that was current then."
    }

    $plain = Join-Path $OutDir ([System.IO.Path]::GetFileNameWithoutExtension($File))
    Write-Host 'Checking and opening...'
    $sha = [JbsBackup]::Open($File, $plain, $pem)
    $pem = $null
    if ($header.sha256 -and $sha -ne $header.sha256) {
        Remove-Item -LiteralPath $plain -Force
        throw 'The opened file does not match the checksum recorded when it was made. Download it again.'
    }
    Write-Host "Opened and checked: $plain" -ForegroundColor Green

    if ($header.kind -eq 'db') {
        $sql = $plain -replace '\.gz$', ''
        $lines = [JbsBackup]::Gunzip($plain, $sql, [bool]$KeepDefiners)
        Write-Host "SQL ($lines lines): $sql" -ForegroundColor Green
        if ($Import -or (Ask 'Import it into this PC''s MySQL now?' $false)) {
            if (-not $Database) { $Database = ('restored_' + $header.site + '_' + $header.date) -replace '[^A-Za-z0-9_]', '_' }
            $connection = @("--host=$MySqlHost", "--port=$MySqlPort", "--user=$MySqlUser", '--default-character-set=utf8mb4')
            if ($AskMySqlPassword) {
                $secure = Read-Host "MySQL password for $MySqlUser" -AsSecureString
                $env:MYSQL_PWD = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto([System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
            }
            try {
                Invoke-MySql ($connection + @('-e', "CREATE DATABASE IF NOT EXISTS ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) $null
                Write-Host "Importing into the database $Database..."
                Invoke-MySql ($connection + @('--max-allowed-packet=1G', $Database)) $sql
            } finally {
                Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
            }
            Write-Host "Imported into $Database on ${MySqlHost}:$MySqlPort." -ForegroundColor Green
        }
    } elseif ($header.kind -eq 'files') {
        if ($Extract -or (Ask 'Unpack the files now?' $true)) {
            $target = Join-Path $OutDir ([System.IO.Path]::GetFileNameWithoutExtension([System.IO.Path]::GetFileNameWithoutExtension($plain)))
            New-Item -ItemType Directory -Force -Path $target | Out-Null
            & "$env:SystemRoot\System32\tar.exe" -xzf $plain -C $target
            if ($LASTEXITCODE -ne 0) { throw "tar could not unpack $plain." }
            Write-Host "Unpacked into $target (paths start from the server account's home folder)." -ForegroundColor Green
        }
    }
    exit 0
} catch {
    # The tool's own reason, not PowerShell's 'Exception calling "Open"' around it
    $reason = $_.Exception
    while ($reason.InnerException) { $reason = $reason.InnerException }
    Write-Host ''
    Write-Host "Stopped: $($reason.Message)" -ForegroundColor Red
    exit 1
}
