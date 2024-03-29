<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

set_error_handler(
  fn ($errno, $errstr, $errfile, $errline) => throw new ErrorException($errstr, $errno, 0, $errfile, $errline)
);

const TEXT_MODE = 'Content-Type: text/plain; charset=utf-8';

// ========================================================================================================================
// DISPLAY DEL CONTENUTO DEI FILE
// ========================================================================================================================

function id($a) {
  return $a;
}

if (array_key_exists("file", $_GET)) {
  $file = htmlspecialchars_decode($_GET["file"]);
  $mime = mime_content_type($file);

  [$mime, $parse] = match (true) {
    strpos($mime, 'text') !== false => ['text/plain; charset=utf-8', 'id'],
    strpos($mime, 'application') !== false => ['text/plain; charset=utf-8', 'htmlspecialchars'],
    default => [$mime, 'id']
  };

  header('Content-Type: ' . $mime);
  echo $parse(file_get_contents($file));
}

if (count($_GET) > 0) {
  die();
}

// ========================================================================================================================
// COMANDI POST
// ========================================================================================================================

const CONFIGFILEJSON = ".seireidire.json";

function save($refresh = false) {
  file_put_contents(CONFIGFILEJSON, json_encode($_POST, JSON_FORCE_OBJECT));
  if ($refresh) {
    header("Location: /");
    die();
  }
}

function apply() {
  $_POST['etichette'] = array_filter(
    $_POST['etichette'],
    fn ($e) => (($b = file_exists($e)) && is_dir($e)) || (!$b && mkdir($e))
  );
  $_POST['associazioni'] = array_intersect($_POST['associazioni'], array_keys($_POST['etichette']));

?>
  <!DOCTYPE html>
  <html>

  <body>
    <table>
      <tr>
        <th>Risultato</th>
        <th>Sorgente</th>
        <th>Destinazione</th>
        <?php
        echo implode("\n", array_map(
          fn ($file, $dir) => "<tr><td>$file</td><td>$dir</td><td>" . json_encode(rename("./$file", "./$dir/$file")) . "</td></tr>",
          array_map('htmlspecialchars_decode', array_keys($_POST['associazioni'])),
          array_map(fn ($k) => $_POST['etichette'][$k], $_POST['associazioni']),
        ));
        ?>
      </tr>
    </table>
  </body>

  </html>
<?php

  $_POST['associazioni'] = [];
  save();
}

if (array_key_exists('command', $_POST)) {
  $cmd = $_POST['command'];
  unset($_POST['command']);
  match ($cmd) {
    "save" => save(true),
    "apply" => apply(),
  };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  die();
}

// ========================================================================================================================
// PAGINA PRINCIPALE
// ========================================================================================================================

$files = array_map(
  'htmlspecialchars',
  array_filter(
    glob('*'),
    fn ($f) => !is_dir($f)
  )
);

try {
  [
    'associazioni' => $associazioni,
    'etichette' => $etichette,
  ] = json_decode(file_get_contents(CONFIGFILEJSON), true);
  foreach (array_diff(array_unique(array_keys($associazioni)), $files) as $file) {
    unset($associazioni[$file]);
  }
} catch (Exception | ValueError) {
  $associazioni = [];
  $etichette = [];
};

?>

<!DOCTYPE html>
<html>

<head>
  <style>
    html,
    body,
    input {
      width: 100%;
      height: 100%;
      margin: 0px;
    }

    body {
      display: grid;
      grid-template-columns: 200px auto 200px;
      gap: 20px;
    }

    #contenuto {
      width: 100%;
      height: 96%;
    }

    #miniature,
    #etichette,
    #controlli {
      overflow-y: scroll;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
      gap: 6px;
      padding: 10px;
    }

    .evidenziatura {
      border: solid blue 2px;
    }

    .selezione {
      border: solid lime 2px;
    }

    #associazioniEtichette {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    #associazioniEtichette span {
      display: flex;
      flex-direction: row;
      justify-content: center;
      align-content: center;
      gap: 6px;
    }

    input.associazione {
      height: 40px;
      width: 40px;
    }
  </style>
</head>

<body>
  <div id="miniature" class="list">
    <?php
    echo implode("\n", array_map(
      fn ($v) => sprintf('
        <a id="associazioni[%s]" target="contenuto" class="miniatura %s"
        onclick="selectAssoc(this);" href="./?file=%s">%s</a>
        ', $v, array_key_exists($v, $associazioni) ? 'evidenziatura' : '', $v, $v, $v),
      $files
    ));
    ?>
  </div>
  <iframe name="contenuto" id="contenuto"></iframe>
  <form id="controlli" action="./" method="post">
    <button type="submit" name="command" value="apply">Applica modifiche</button>
    <button type="submit" name="command" value="save">Salva</button>
    <button type="button" onclick="newEtichetta()">Nuova directory</button>
    <div id="associazioniEtichette">
      <?php
      foreach ($etichette as $k => $e) {
        $etichetteRadio = '';
        $etichetteText = "<input class='etichetta' type='text' name='etichette[$k]' value='$e'>\n";
        foreach ($files as $f) {
          $e = htmlspecialchars($e);
          $selezione = array_key_exists($f, $associazioni) && ($associazioni[$f] == $k) ? 'checked' : '';
          $etichetteRadio .= "<input hidden
          class='associazione' type='radio'
          name='associazioni[$f]' value='$k' $selezione
          onclick='phpNewAssociazione(this)'
          >\n";
        }
        echo "<span> $etichetteRadio $etichetteText </span>";
      }
      ?>
    </div>
  </form>

  <script>
    let primocheck = false;

    const miniature = () => Object.values(document.getElementById('miniature').children);
    const etichette = () => Object.values(document.getElementsByClassName('etichetta'));
    const associazioni = () => Object.values(document.getElementsByClassName('associazione'));

    function selectAssoc(elem) {
      // Seleziona il file
      const selezione = 'selezione';
      miniature().filter((elem) => elem.classList.contains(selezione))
        .forEach((elem) => elem.classList.remove(selezione));
      elem.classList.add(selezione);

      // Ottieni l'associazione
      const file = elem.id;
      const cache = associazioni();
      cache.forEach((elem) => elem.hidden = true);
      const display = cache.filter((elem) => elem.name == file);
      display.forEach((elem) => elem.hidden = false);
      primocheck = !display.reduce((a, elem) => a || elem.checked, false);
    }

    function clickPrimoNonEvidenziato() {
      miniature().filter((elem) => !elem.classList.contains('evidenziatura'))
        .slice(0, 1).forEach((elem) => elem.click());
    }

    clickPrimoNonEvidenziato();

    function phpNewAssociazione(elem) {
      document.getElementById(elem.name).classList.add('evidenziatura');
      if (primocheck) {
        clickPrimoNonEvidenziato();
      }
    }

    function newEtichetta() {
      const idx = 1 + etichette().map((e) => Number(/etichette\[(\d+)\]/.exec(e.name)[1]))
        .reduce((a, b) => Math.max(a, b), -1);

      const line = document.createElement('span');

      miniature().map(function(f) {
        const e = document.createElement('input');
        e.hidden = true;
        e.classList.add('associazione');
        e.type = 'radio';
        e.name = `${f.id}`;
        e.value = `${idx}`;
        e.onclick = () => phpNewAssociazione(e);
        line.appendChild(e);
      });

      const e = document.createElement('input');
      e.type = 'text';
      e.classList.add('etichetta');
      e.name = `etichette[${idx}]`;
      line.appendChild(e);

      associazioniEtichette.appendChild(line);

      miniature().filter((elem) => elem.classList.contains('selezione'))
        .slice(0, 1).forEach((elem) => elem.click());
    }
  </script>

</body>

</html>

<?php
// ========================================================================================================================
// ROUTER PER LA STAMPA DELLE IMMAGINI
// ========================================================================================================================

$req = $_SERVER['REQUEST_URI'];
if (strpos($req, '?') !== false) {
  return false;
}
