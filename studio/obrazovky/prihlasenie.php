<?php $spat = $_GET['spat'] ?? '/'; ?>
<section class="login">
  <svg class="logo-velke" viewBox="0 0 112 112" aria-hidden="true"><path d="M56 6c-24 0-40 17-40 39 0 28 40 60 40 60s40-32 40-60c0-22-16-39-40-39z" fill="#1f6f5a"/><g fill="none" stroke="#f3efe4" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"><circle cx="56" cy="22" r="6.5" fill="#f3efe4" stroke="none"/><path d="M56 31v30M56 40l-12-13M56 40l12-13M56 61v24M56 61l11 8-11 7"/></g></svg>
  <h1>Studio pre jogovne</h1>
  <p>Správa profilu, služieb, článkov a štatistík vašej jogovne na najdijogu.sk.</p>
  <a class="btn primar google" href="/api/oauth.php?spat=<?= urlencode($spat) ?>"><svg viewBox="0 0 24 24" width="18" height="18"><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.7-2.4 3.6v3h3.9c2.3-2.1 3.5-5.2 3.5-8.8z"/><path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-2.9l-3.9-3c-1.1.7-2.5 1.2-4.1 1.2-3.1 0-5.8-2.1-6.7-5H1.3v3.1C3.3 21.3 7.3 24 12 24z"/><path fill="#FBBC05" d="M5.3 14.3c-.2-.7-.4-1.5-.4-2.3s.1-1.6.4-2.3V6.6H1.3C.5 8.2 0 10 0 12s.5 3.8 1.3 5.4l4-3.1z"/><path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4C18 1.2 15.2 0 12 0 7.3 0 3.3 2.7 1.3 6.6l4 3.1c.9-2.9 3.6-4.9 6.7-4.9z"/></svg> Prihlásiť sa cez Google</a>
  <p class="drobne">Nemáte ešte jogovňu na najdijogu.sk? <a href="https://najdijogu.sk/jogovne/pridat">Prihláste ju</a> — je to zadarmo.</p>
</section>
