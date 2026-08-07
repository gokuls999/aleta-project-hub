<?php
require_once __DIR__ . '/../src/perms.php';
require_once __DIR__ . '/../src/ui.php';
$user = require_admin();

$saved = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $look = in_array($_POST['look'] ?? '', ['clay','glass','liquid'], true) ? $_POST['look'] : 'clay';
    $pal  = (string)(int)($_POST['palette'] ?? 1); if ((int)$pal < 1 || (int)$pal > 10) $pal = '1';
    $font = (string)(int)($_POST['font'] ?? 1);    if ((int)$font < 1 || (int)$font > 8) $font = '1';
    setting_set('theme_look', $look);
    setting_set('theme_palette', $pal);
    setting_set('theme_font', $font);
    header('Location: appearance.php?saved=1'); exit;
}
$cur = theme();
$saved = isset($_GET['saved']);

$looks = [
    'clay'   => ['Claymorphism', 'Soft puffy 3D surfaces'],
    'glass'  => ['Glassmorphism', 'Frosted translucent panels'],
    'liquid' => ['Liquid Glass', 'Deep glossy blur & sheen'],
];
$palettes = [
    1=>['Grape','#6c5ce7','#a06bf0'], 2=>['Ocean','#2f8fd6','#22b8c4'], 3=>['Sunset','#ff6b6b','#ff9f45'],
    4=>['Forest','#2fae66','#59c273'], 5=>['Rose','#e05a9c','#f06ab0'], 6=>['Midnight','#5b5bd6','#7a6bf0'],
    7=>['Candy','#f76ba8','#6ca8f7'], 8=>['Mono','#5a5a70','#8a8aa0'], 9=>['Coral','#ff6f61','#ff9472'],
    10=>['Aqua','#12b8b0','#2fd0c0'],
];
$fonts = [
    1=>['Rounded','"Fredoka","Nunito",sans-serif','Medium'], 2=>['Poppins','"Poppins",sans-serif','Medium'],
    3=>['Inter','"Inter",sans-serif','Small'], 4=>['Quicksand','"Quicksand",sans-serif','Medium'],
    5=>['Baloo 2','"Baloo 2",sans-serif','Big'], 6=>['Work Sans','"Work Sans",sans-serif','Small'],
    7=>['Sora','"Sora",sans-serif','Medium'], 8=>['Space Grotesk','"Space Grotesk",sans-serif','Large'],
];
$csrf = csrf_token();

ui_head('Appearance — Aleta Work Tracker');
ui_topbar($user, 'appearance.php');
ui_page_head('Appearance', 'Choose the look, colour template and font — applies to everyone. Preview updates live; press Save to keep it.');
?>
    <?php if ($saved): ?><div class="note good">Saved — the whole app now uses this theme.</div><?php endif; ?>
    <form method="post" id="themeForm">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="look" id="fLook" value="<?= e($cur['look']) ?>">
      <input type="hidden" name="palette" id="fPalette" value="<?= e($cur['palette']) ?>">
      <input type="hidden" name="font" id="fFont" value="<?= e($cur['font']) ?>">

      <div class="card">
        <div class="section-title">1 · Look</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap">
          <?php foreach ($looks as $k => $l): ?>
          <div class="opt look-opt <?= $cur['look']===$k?'selected':'' ?>" data-look="<?= $k ?>"
               style="flex:1;min-width:180px;padding:18px;border-radius:18px;cursor:pointer;box-shadow:var(--raise-shadow)">
            <div style="font-family:var(--font-head);font-weight:700;font-size:17px"><?= e($l[0]) ?></div>
            <div class="muted" style="font-size:13px;margin-top:3px"><?= e($l[1]) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="section-title">2 · Colour template</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:14px">
          <?php foreach ($palettes as $i => $p): ?>
          <div class="opt pal-opt <?= (int)$cur['palette']===$i?'selected':'' ?>" data-palette="<?= $i ?>"
               style="cursor:pointer;border-radius:16px;overflow:hidden;box-shadow:var(--raise-shadow)">
            <div style="height:52px;background:linear-gradient(135deg,<?= $p[1] ?>,<?= $p[2] ?>)"></div>
            <div style="padding:8px 10px;font-weight:700;font-size:13px"><?= e($p[0]) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="section-title">3 · Font</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px">
          <?php foreach ($fonts as $i => $f): ?>
          <div class="opt font-opt <?= (int)$cur['font']===$i?'selected':'' ?>" data-font="<?= $i ?>"
               style="cursor:pointer;padding:16px;border-radius:16px;box-shadow:var(--raise-shadow);font-family:<?= $f[1] ?>">
            <div style="font-size:22px;font-weight:700">Aa <span style="font-size:14px" class="muted"><?= e($f[2]) ?></span></div>
            <div style="font-size:14px;margin-top:2px"><?= e($f[0]) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="margin-top:20px;display:flex;gap:12px;align-items:center">
        <button class="btn" type="submit">Save appearance</button>
        <span class="muted" style="font-size:13px">Live preview is active — Save to apply for all staff.</span>
      </div>
    </form>

    <style>
      .opt.selected { outline: 3px solid var(--accent); outline-offset: 2px; }
      .opt { transition: transform .1s; } .opt:hover { transform: translateY(-2px); }
    </style>
    <script>
      const body = document.body;
      function pick(cls, attr, formId) {
        document.querySelectorAll('.'+cls).forEach(el => {
          el.addEventListener('click', () => {
            document.querySelectorAll('.'+cls).forEach(x => x.classList.remove('selected'));
            el.classList.add('selected');
            const v = el.getAttribute('data-'+attr);
            body.setAttribute('data-'+attr, v);           // live preview
            document.getElementById(formId).value = v;     // persist on save
          });
        });
      }
      pick('look-opt', 'look', 'fLook');
      pick('pal-opt', 'palette', 'fPalette');
      pick('font-opt', 'font', 'fFont');
    </script>
<?php ui_foot();
