#!/usr/bin/php -q
<?php
/**
 * @author Alejandro Moraga <moraga86@gmail.com>
 */

header('content-type: text/plain; charset=utf-8');

$db = [];

// verb suffix
$vnd = [
    // presente
    'amos',
    'ais',
    // perfeito
    'este', 'emos', 'estes', 'eram',
    'aste', 'ou', 'astes',
    // pretério imperfeito
    'ava', 'avas', 'avamos', 'aveis', 'avam',
    'ia', 'ias',
    // pretério + perfeito
    'era', 'eras', 'eramos', 'ereis',
    'ara', 'aras', 'aramos', 'areis', 'aram',
    // futuro
    'reis',
    'arei', 'aras', 'ara', 'aremos', 'areis', 'arao',
    'erao',
    // futuro do pretérito
    'aria', 'arias', 'ariamos', 'arieis', 'ariam',
    // conjuntivo presente
    'amos', 'ais', 'am',
    'uemos', 'ueis', 'uem',
    'iramos',
    'ueis',
    // pretérito imperfeito
    'esse', 'esses', 'essemos', 'esseis', 'essem', // fosseis
    'asse', 'asses', 'assemos', 'asseis', 'assem',
    // conjuntivo futuro
    'eres', 'ermos', 'erdes', 'erem',
    'ares', 'armos', 'ardes', 'arem',
    // conjuntivo futuro do pretério
    'eria', 'eriamos', 'erieis',
    'iamos', 'ieis',
    // infinitivo
    'ar',
    // gerundio
    'ando', 'endo',
];

usort($vnd, function($a, $b) {
    $an = strlen($a);
    $bn = strlen($b);
    if ($an == $bn)
        return 0;
    return $an > $bn ? 1 : -1;
});

$vnd = array_map(function($str) { return [$str, strlen($str) * -1]; }, $vnd);
$vln = count($vnd);


// insert('x d z b c d e');
//
// insert('a b c x y z d d f j h k');
// // insert('a c b x d f j h k');
//
// search('x y z');


// printdb($db);

function insert($str) {
    global $db;
    $db[] = [$str, reduce($str), max_reduce($str)];
}

function reduce($str) {
    # mais próximo do original
    preg_match_all('#[0-9]+(?:[,.-][0-9]+)*|[a-zà-ú]+[a-zà-ú0-9]*(?:-[0-9a-zà-ú]+)*#', strtolower($str), $matches);
    return $matches[0];
}

function max_reduce($str) {
    # redução máxima
    preg_match_all('#[0-9]+(?:[,.-][0-9]+)*|[a-z]+[a-z0-9]*(?:-[0-9a-z]+)*#', strtolower(unaccent($str)), $matches);
    return array_map('wstem', $matches[0]);
}

function wstem($str) {
    $ret = verbstem($str);
    return $ret != $str ? $ret : pluraltosingular($ret);
}

function search($str) {
    global $db;

    $re = reduce($str);
    $mx = max_reduce($str);

    $best = [0, ''];

    foreach ($db as $row) {
        $score = match($re, $mx, $row[1], $row[2]);
        // echo "$score\n". implode(' ', $row[1]) . "\n\n";
        if ($best[0] < $score)
            $best = [$score, $row[0]];
    }

    // echo implode(' :: ', $best);

    return $best[0] ? $best[1] : '';
}

function printdb() {
    global $db;
    foreach ($db as $row) {
        echo "(O) " . $row[0] . "\n(R) " . implode(' ', $row[1]) ."\n(X) ". implode(' ', $row[2]) . "\n\n";
    }
}

function match($qre, $qrx, $hre, $hmx, $ppp=-1, $step=1, &$psc=0) {
    $re = array_shift($qre);
    $mx = array_shift($qrx);
    $size = count($hre);
    $arr = $hmx;
    $pos = [];
    $cnt = 0;
    $sum = 0;
    $score = 0;

    // count matches
    while (($index = array_search($mx, $arr)) !== false) {
        $pos[] = $sum + $index;
        $arr = array_slice($arr, $index + 1);
        $sum += $index + 1;
        ++$cnt;
    }

    if ($cnt) {
        for ($prv = 0, $j = 0; $j < $cnt; ++$j) {
            $cur = $pos[$j];
            $val = $cur - $ppp;
            if ($val != 1) {
                // log e x 2 x N
                // $val = 1 / log(($val > 0 ? $val - 1 : $val * -1) + 2, 2);
                $val = exp($val > 0 ? ($val - 1) * -1 : $val);
            }
            $val /= $step;
            // text similarity
            // $val = $val / 2 + 1 / (1 + levenshtein($re, $hre[$index])) * .5;
            $score += $val;
            $lim = $j + 1 == $cnt ? $size : $pos[$j + 1];
            $rs1 = array_slice($hre, $prv, $lim - $prv);
            $rs2 = array_slice($hmx, $prv, $lim - $prv);
            if ($qre)
                $score += match($qre, $qrx, $rs1, $rs2, $cur - $prv, $step + 1, $score);
            $prv = $cur + 1;
        }
    }
    else if ($qre) {
        $psc /= $step;
        $score += match($qre, $qrx, $hre, $hmx, -1, $step + 1, $score);
    }
    else {
        $psc /= $step;
    }

    return $score;
}

function unaccent($str) {
    return preg_replace(
        '#&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);#i', '$1',
            htmlentities($str, ENT_QUOTES, 'UTF-8'));
}

function verbstem($str) {
    global $vnd, $vln;

    for ($i = $vln; $i--; ) {
        if (substr($str, $vnd[$i][1]) == $vnd[$i][0] && strpos('aeiou', substr($str, $vnd[$i][1] -1, 1)) === false) {
            $str = substr($str, 0, $vnd[$i][1]);
            // fiz
            if (substr($str, -2) == 'iz')
                $str = substr($str, 0, -2) . 'a';
            // façamos
            else if (substr($str, -1) == 'c')
                $str = substr($str, 0, -1);
            else if (substr($str, -2) == 'is')
                $str = substr($str, 0, -2) . 'er';
            else if (substr($str, -3) == 'eir')
                $str = substr($str, 0, -2) . 'r';
            // apenas uma letra
            else if (strlen($str) == 1)
                $str .= $vnd[$i][0]{0};
            return $str;
        }
    }

    return $str;
}

// print_r(array_map('verbstem', [
//     'gosto', 'gostaria',
//     'iria', 'irias', 'irieis', 'iremos', 'iriamos',
//     'estareis', 'estamos', 'estaremos',
//     'solicitaremos', 'solicitando', 'solicitamos', 'solicitar',
//     'entregue', 'entregar', 'entreguemos', 'entregassem', 'entregueis',
//     'gostassemos', 'gostando', 'gostaramos', 'gostastes', 'gostavamos', 'gostaveis',
//     'quererieis', 'quereriamos', 'quereria', 'querermos', 'queiram',
//     'quiserdes', 'quererao', 'quisermos', 'queiramos', 'queiram', 'quisessemos',
//     'quiseramos', 'quereras', 'quiseste', 'quiseras', 'queremos', 'gostamos',
//     'depositamos', 'depositemos', 'depositaramos', 'depositaste', 'depositavamos',
//     'fazia', 'farieis', 'fizeramos', 'fizessemos', 'fizerdes', 'fariamos', 'fareis', 'faremos', 'facamos', 'fizeres',
// ]));
//

function pluraltosingular($str) {
	if (substr($str, -1) != 's')
		return $str;
	// albuns batons marrons
	if (substr($str, -2, 1) == 'n')
		return substr($str, 0, -2) . 'm';
	// flores gizes vezes tenis
	else if (strpos('aeou', substr($str, 0, 1)) === false && substr($str, -2, 1) == 'e' && strpos('nrsz', substr($str, -3, 1)) !== false)
		return substr($str, 0, -2);
	// aneis anzois jornais
	else if (substr($str, -2) == 'is' && strpos('aeiou', substr($str, -3, 1)) !== false)
		return substr($str, 0, -2) . 'l';
	// frances portugues
	else if (substr($str, -2) == 'es' && strpos('cluv', substr($str, -3, 1)) !== false)
		return $str;
	// caes paes
	else if (substr($str, -3) == 'aes')
		return substr($str, 0, -2) . 'o';
	// leoes
	else if (substr($str, -3) == 'oes')
		return substr($str, 0, -3) . 'ao';
	// exceto onibus lapis tenis arvores
	else if (strpos('ius', substr($str, -2, 1)) === false && substr($str, -3, 1) != 'n')
		return substr($str, 0, -1);
	return $str;
}

// print_r(array_map('pluraltosingular', [
//     'cachorros', 'acoes', 'patos', 'leoes', 'portugues', 'lapis', 'jornais',
// ]));


// // Socket server DEMO
//
// set_time_limit(0);
//
// ob_implicit_flush();
//
// $address = '127.0.0.1';
// $port = 1000;
//
// if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
//     echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
//
// if (socket_bind($sock, $address, $port) === false)
//     echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
//
// if (socket_listen($sock, 5) === false)
//     echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
//
// do {
//     if (($msgsock = socket_accept($sock)) === false) {
//         echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
//         break;
//     }
//
//     do {
//         if (false === ($buf = socket_read($msgsock, 2048, PHP_NORMAL_READ))) {
//             echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($msgsock)) . "\n";
//             break 2;
//         }
//
//         if (!$buf = trim($buf))
//             continue;
//
//         if ($buf == 'quit')
//             break;
//
//         if ($buf == 'shutdown') {
//             socket_close($msgsock);
//             break 2;
//         }
//
//         switch (substr($buf, 0, 3)) {
//             case 'INS':
//                 insert(substr($buf, 4));
//                 $back = 'OK';
//                 break;
//
//             default:
//                 $s = microtime(true);
//                 // $back = search(substr($buf, 4)) ?: 'ERR not found';
//                 $back = search($buf) ?: 'ERR not found';
//                 $back .= "\ntook " . number_format(microtime(true) - $s, 5) . 's';
//                 break;
//         }
//
//         $back .= "\n";
//
//         socket_write($msgsock, $back, strlen($back));
//     } while (true);
//     socket_close($msgsock);
// } while (true);
//
// socket_close($sock);

?>
