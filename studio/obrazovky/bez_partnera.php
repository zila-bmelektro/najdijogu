<section class="login">
  <h1>Účet <?= h($ja['email']) ?> zatiaľ nemá jogovňu</h1>
  <p>Tento Google účet nie je priradený k žiadnej jogovni. Ak ste jogovňu už prihlásili iným e-mailom, napíšte nám a priradíme ju. Inak ju prihláste teraz — trvá to minútu.</p>
  <div class="akcie"><a class="btn primar" href="https://najdijogu.sk/jogovne/pridat">Prihlásiť jogovňu</a><a class="btn" href="mailto:info@najdijogu.sk?subject=Priradenie%20jogovne%20k%20<?= urlencode($ja['email']) ?>">Napísať nám</a></div>
</section>
