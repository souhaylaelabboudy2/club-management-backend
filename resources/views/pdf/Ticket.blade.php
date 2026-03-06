<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    background: #0a0f1e;
    padding: 28px;
    color: #1e293b;
  }

  /* ── Outer wrapper ── */
  .wrap {
    max-width: 520px;
    margin: 0 auto;
  }

  /* ══════════════════════════════════════
     TICKET CARD
  ══════════════════════════════════════ */
  .ticket {
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,0.45);
  }

  /* ── Top accent line ── */
  .accent-bar {
    height: 5px;
    background: #c0392b;
  }

  /* ── Header ── */
  .header {
    background: #0f1e3d;
    padding: 30px 28px 22px;
  }

  /* club row: logo + name + event name */
  .club-row {
    width: 100%;
    margin-bottom: 18px;
  }
  .club-logo-td {
    width: 64px;
    vertical-align: middle;
  }
  .club-logo-box {
    width: 58px;
    height: 58px;
    border-radius: 12px;
    border: 2px solid rgba(255,255,255,0.18);
    overflow: hidden;
    background: #162944;
    text-align: center;
    line-height: 58px;
    font-size: 26px;
    color: #fff;
    font-weight: 800;
  }
  .club-logo-box img {
    width: 58px;
    height: 58px;
    display: block;
    object-fit: cover;
  }
  .club-text-td {
    vertical-align: middle;
    padding-left: 14px;
  }
  .club-label {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.4);
    margin-bottom: 5px;
  }
  .event-name {
    font-size: 19px;
    font-weight: 800;
    color: #f8fafc;
    line-height: 1.2;
    letter-spacing: -0.02em;
  }

  /* badge */
  .badge {
    display: inline-block;
    background: rgba(231,76,60,0.2);
    border: 1px solid rgba(231,76,60,0.5);
    color: #fca5a5;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    padding: 4px 12px;
    border-radius: 99px;
    margin-top: 4px;
  }

  /* ── Tear line ── */
  .tear {
    background: #f0f4ff;
    height: 20px;
    position: relative;
  }
  .tear-line {
    border-top: 2px dashed #c7d2fe;
    position: absolute;
    left: 20px;
    right: 20px;
    top: 9px;
  }

  /* ── Body ── */
  .body {
    padding: 22px 26px 18px;
  }

  /* member highlight box */
  .member-box {
    background: #fef9ec;
    border: 2px solid #f59e0b;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 20px;
    text-align: center;
  }
  .member-lbl {
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #92400e;
    margin-bottom: 5px;
  }
  .member-name {
    font-size: 20px;
    font-weight: 800;
    color: #78350f;
    letter-spacing: -0.01em;
  }

  /* info rows */
  .info-row {
    width: 100%;
    margin-bottom: 10px;
    background: #f8faff;
    border: 1px solid #e0e7ff;
    border-radius: 10px;
  }
  .icon-td {
    width: 40px;
    vertical-align: middle;
    text-align: center;
    font-size: 16px;
    padding: 12px 0 12px 12px;
  }
  .info-td {
    vertical-align: middle;
    padding: 12px 14px;
  }
  .info-lbl {
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 3px;
  }
  .info-val {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.4;
  }

  /* ── Divider ── */
  .divider {
    height: 1px;
    background: #e2e8f0;
    margin: 18px 0;
  }

  /* ── QR section ── */
  .qr-section {
    text-align: center;
    padding: 6px 0 18px;
  }
  .qr-title {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #475569;
    margin-bottom: 4px;
  }
  .qr-hint {
    font-size: 10px;
    color: #94a3b8;
    margin-bottom: 16px;
  }
  .qr-box {
    width: 170px;
    height: 170px;
    margin: 0 auto 14px;
    padding: 10px;
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
  }
  .qr-box img {
    width: 148px;
    height: 148px;
    display: block;
  }

  /* ── Code strip ── */
  .code-strip {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 9px 20px;
    display: inline-block;
    font-family: 'DejaVu Sans Mono', 'Courier New', monospace;
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 0.16em;
    color: #1e293b;
  }

  /* ── Info footer strip ── */
  .info-strip {
    background: #f8faff;
    border-top: 1px solid #e2e8f0;
    padding: 14px 26px;
  }
  .info-strip-text {
    font-size: 10px;
    color: #64748b;
    text-align: center;
    line-height: 1.8;
  }
  .info-strip-text strong {
    color: #1e293b;
  }

  /* ── Footer ── */
  .footer {
    background: #c0392b;
    padding: 10px 26px;
  }
  .footer-inner {
    width: 100%;
  }
  .footer-brand-td {
    vertical-align: middle;
  }
  .footer-brand {
    font-size: 14px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.01em;
  }
  .footer-right-td {
    vertical-align: middle;
    text-align: right;
  }
  .footer-hint {
    font-size: 8px;
    color: rgba(255,255,255,0.65);
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  /* ── Generated line below card ── */
  .generated {
    text-align: center;
    margin-top: 14px;
    font-size: 9px;
    color: rgba(255,255,255,0.3);
    letter-spacing: 0.06em;
  }
</style>
</head>
<body>
<div class="wrap">
<div class="ticket">

  {{-- ── Top accent ── --}}
  <div class="accent-bar"></div>

  {{-- ── Header ── --}}
  <div class="header">
    <table class="club-row"><tr>
      <td class="club-logo-td">
        <div class="club-logo-box">
          @if($clubLogo && file_exists($clubLogo))
            <img src="{{ $clubLogo }}" alt="">
          @else
            {{ mb_strtoupper(mb_substr($ticket->club_name ?? 'C', 0, 1)) }}
          @endif
        </div>
      </td>
      <td class="club-text-td">
        <div class="club-label">{{ $ticket->club_name ?? 'CluVersity' }}</div>
        <div class="event-name">{{ $ticket->event_title }}</div>
      </td>
    </tr></table>
    <div class="badge">Billet d'entree officiel</div>
  </div>

  {{-- ── Tear line ── --}}
  <div class="tear"><div class="tear-line"></div></div>

  {{-- ── Body ── --}}
  <div class="body">

    {{-- Member box --}}
    <div class="member-box">
      <div class="member-lbl">Titulaire du billet</div>
      <div class="member-name">{{ $ticket->first_name }} {{ $ticket->last_name }}</div>
    </div>

    {{-- Info rows --}}
    <table class="info-row"><tr>
      <td class="icon-td">&#128197;</td>
      <td class="info-td">
        <div class="info-lbl">Date &amp; Heure</div>
        <div class="info-val">{{ \Carbon\Carbon::parse($ticket->event_date)->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm') }}</div>
      </td>
    </tr></table>

    <table class="info-row"><tr>
      <td class="icon-td">&#128205;</td>
      <td class="info-td">
        <div class="info-lbl">Lieu</div>
        <div class="info-val">{{ $ticket->event_location }}</div>
      </td>
    </tr></table>

    <table class="info-row"><tr>
      <td class="icon-td">&#127968;</td>
      <td class="info-td">
        <div class="info-lbl">Organisateur</div>
        <div class="info-val">{{ $ticket->club_name ?? 'CluVersity' }}</div>
      </td>
    </tr></table>

    <div class="divider"></div>

    {{-- QR code --}}
    <div class="qr-section">
      <div class="qr-title">Code QR d'acces</div>
      <div class="qr-hint">Presentez ce code a l'entree pour validation</div>
      <div class="qr-box">
        <img src="data:image/svg+xml;base64,{{ $qrCodeBase64 }}" alt="QR Code">
      </div>
      <div class="code-strip">{{ $ticketCode }}</div>
    </div>

  </div>

  {{-- ── Info strip ── --}}
  <div class="info-strip">
    <div class="info-strip-text">
      <strong>Important :</strong> Ce billet est strictement personnel et non transferable.<br>
      Presentez-le a l'entree de l'evenement. Arrivez 15 min avant le debut.
    </div>
  </div>

  {{-- ── Footer ── --}}
  <div class="footer">
    <table class="footer-inner"><tr>
      <td class="footer-brand-td">
        <div class="footer-brand">CluVersity</div>
      </td>
      <td class="footer-right-td">
        <div class="footer-hint">EST Fes · Billet valide une seule fois</div>
      </td>
    </tr></table>
  </div>

</div>

<div class="generated">
  Genere le {{ now()->format('d/m/Y a H:i') }}
</div>

</div>
</body>
</html>