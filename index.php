<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

set_error_handler(
  fn ($errno, $errstr, $errfile, $errline) => throw new ErrorException($errstr, $errno, 0, $errfile, $errline)
);

const TEXT_MODE = 'Content-Type: text/plain; charset=utf-8';

// ========================================================================================================================
// QOL
// ========================================================================================================================

const map = 0;
const filter = 3;
const join = 6;
const values = 7;

function stream($collection, $pipeline) {
  $ris = [];
  foreach ($collection as $k => $c) {
    foreach ($pipeline as $line) {
      [$op, $arg] = $line;
      switch ($op) {
        case map:
          $c = $arg($k, $c);
          break;
        case filter:
          if ($arg($k, $c)) {
            break;
          } else {
            continue 3;
          }
        default:
          break;
      }
    }
    $ris[$k] = $c;
  }
  [$op, $arg] = $pipeline[array_key_last($pipeline)];
  return match ($op) {
    join => implode($arg, $ris),
    values => array_values($ris),
    default => $ris,
  };
}

// ========================================================================================================================
// DISPLAY DEL CONTENUTO DEI FILE
// ========================================================================================================================

session_start();

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
  header(TEXT_MODE);
  print_r($_POST);

  //file_put_contents(CONFIGFILEJSON, json_encode($_SESSION, JSON_FORCE_OBJECT));
  //if ($refresh) {
  //  header("Location: /");
  //  die();
  //}
}

const htmlTableRow = '
      <tr>
        <td>{{RIS}}</td>
        <td>{{SRC}}</td>
        <td>{{DST}}</td>
      </tr>
  ';
const mTableRow = ['{{RIS}}', '{{SRC}}', '{{DST}}'];

function apply() {
  $ris = '';
  save();
?>
  <!DOCTYPE html>
  <html>

  <body>
    <table>
      <tr>
        <th>Risultato</th>
        <th>Sorgente</th>
        <th>Destinazione</th>
        <?php echo $ris; ?>
      </tr>
    </table>
  </body>

  </html>
<?php
}

if (array_key_exists('command', $_POST)) {
  match ($_POST['command']) {
    "save" => save(true),
    "apply" => apply(),
  };
}

if (count($_POST) != 0) {
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
      grid-template:
        'miniature contenuto controlli' min-content
        'miniature contenuto etichette' 1fr
        / 200px 1fr 200px;
      gap: 20px;
      justify-content: space-around;
      align-content: space-around;
    }

    #miniature {
      grid-area: miniature;
      padding: 10px;
    }

    #contenuto {
      grid-area: contenuto;
      width: 100%;
      height: 96%;
    }

    #controlli {
      grid-area: controlli;
      padding: 10px;
    }

    #etichette {
      grid-area: etichette;
      padding: 10px;
    }

    #miniature,
    #etichette,
    #controlli {
      overflow-y: scroll;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .evidenziatura {
      border: solid blue 2px;
    }

    .selezione {
      border: solid lime 2px;
    }

    .etichetta {
      display: grid;
      grid-template-columns: 30px 1fr;
      grid-template-rows: 30px;
      gap: 6px;
    }

    #etichette,
    #associazioni {
      display: flex;
      flex-direction: column;
    }

    #associazioniEtichette {
      display: flex;
      flex-direction: row;
    }

    input.etichettaRadio {
      height: 20px;
      width: 20px;
    }
  </style>
</head>

<body>
  <div id="miniature" class="list">
    <?php
    echo implode("\n", array_map(
      fn ($v) => sprintf('
        <a id="file[%s]" target="contenuto" class="miniatura %s"
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
    <input id="newEtichettaText" type="text">
    <div id="associazioniEtichette">
      <?php
      $etichettaRadio = '';
      $etichetteText = '';
      foreach ($etichette as $k => $e) {
        $etichetteText .= "<input type='text' name='etichetta[$k]' value='$e'>\n";
        foreach ($files as $f) {
          $e = htmlspecialchars($e);
          $selezione = array_key_exists($f, $associazioni) ? 'selected' : '';
          $etichettaRadio .= "<input hidden
          class='etichettaRadio' type='radio'
          name='file[$f]' value='$k' $selezione value='$e'
          onclick='phpNewAssociazione(this)'
          >\n";
        }
      }
      echo "<fieldset id='associazioni'>$etichettaRadio</fieldset>";
      echo "<fieldset id='etichette'>$etichetteText</fieldset>";
      ?>
    </div>
  </form>
</body>

<script>
  let primocheck = false;

  function selectAssoc(elem) {
    // Seleziona il file
    const selezione = 'selezione';
    Object.values(miniature.children)
      .filter((elem) => elem.classList.contains(selezione))
      .forEach((elem) => elem.classList.remove(selezione));
    elem.classList.add(selezione);

    // Ottieni l'associazione
    const file = elem.id;
    const cache = Object.values(associazioni.children);
    cache.forEach((elem) => elem.hidden = true);
    const display = cache.filter((elem) => elem.name == file);
    display.forEach((elem) => elem.hidden = false);
    primocheck = !display.reduce((a, elem) => a || elem.checked, false);
  }

  function clickPrimoNonEvidenziato() {
    Object.values(miniature.children)
      .filter((elem) => !elem.classList.contains('evidenziatura'))
      .slice(0, 1).forEach((elem) => elem.click());
  }

  clickPrimoNonEvidenziato();

  function phpNewAssociazione(elem) {
    document.getElementById(elem.name).classList.add('evidenziatura');
    if (primocheck) {
      clickPrimoNonEvidenziato();
    }
  }

  // TODO nuova etichetta
</script>

</html>

<?php
// ========================================================================================================================
// ROUTER PER LA STAMPA DELLE IMMAGINI
// ========================================================================================================================

$req = $_SERVER['REQUEST_URI'];
if (strpos($req, '?') !== false) {
  return false;
}
