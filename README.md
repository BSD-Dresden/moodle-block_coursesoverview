# Kursfortschritt (block_coursesoverview)

Moodle-Block für interne Zwecke. Er zeigt auf der Kursseite den Fortschritt an —
Teilnehmenden ihren eigenen, Organisatoren den ihrer Gruppe.

Gerechnet wird über die **Abschlusskriterien des Kurses**. Die sind für alle
gleich, deshalb sagen Kursseite, Teilnehmerliste und Excel-Export dieselbe Zahl.
Das ist der Unterschied zu `block_completion_progress`, der über die gerade
*sichtbaren* Aktivitäten rechnet: Wer durch Zugriffsbeschränkungen erst fünf von
sechzehn Schritten sieht und vier davon erledigt hat, steht dort bei 80 % und
hier bei 25 %.

## Voraussetzung

Moodle 4.5+, PHP 8.1+, dazu `local_coursesoverview` — von dort kommen die Zahlen.
Die Abhängigkeit steht in der `version.php`, Moodle installiert den Block ohne
sie gar nicht erst.

## Installation

Nach `blocks/coursesoverview` entpacken, dann:

```bash
php admin/cli/upgrade.php --non-interactive
```

## Platzierung

Der Block darf in jede Blockregion, gehört aber nicht in die rechte Leiste: Die
lässt sich einklappen, und dann ist er weg. Boost Union bietet zusätzliche
Regionen an, darunter **`content-upper`** — die liegt innerhalb der Hauptspalte
über dem Kursinhalt und lässt sich nicht wegklappen.

Freischalten unter *Darstellung → Boost Union → Layout → Blockregionen*, dort
`content-upper` für das Kurslayout auswählen. Danach steht die Region beim
Bearbeiten der Kursseite unter „Block hinzufügen" zur Verfügung.

## Die zwei Ansichten

Welche jemand sieht, entscheidet `local/coursesoverview:view` **im Kurskontext**
— dieselbe Berechtigung, die auch die Teilnehmerliste öffnet.

**Teilnehmende** sehen einen Balken mit einem Kästchen je Abschlusskriterium:
grün erledigt, rot offen und jetzt machbar, grau noch nicht freigeschaltet.
Gesperrte Schritte werden mitgezählt, aber nicht benannt — die Länge des Weges
ist sichtbar, der Inhalt nicht. Dazu der nächste offene Schritt als Link und die
verbleibende Zeit bis Kursende.

**Organisatoren** sehen, wie viele fertig sind, und namentlich die, die es noch
nicht sind, mit dem wenigsten Fortschritt zuerst. Darunter Links auf die volle
Liste und den Excel-Export.

## Gruppen

Wer `moodle/site:accessallgroups` hat, sieht den ganzen Kurs. Wer in Gruppen
ist, sieht seine Gruppen — so bleiben mehrere Organisationsabteilungen innerhalb
eines Kurses getrennt. Wer in keiner Gruppe ist, betreut den Kurs als Ganzes und
sieht alle.

## Aufbau

```
block_coursesoverview.php   Blockklasse, wählt die Ansicht
classes/view.php            baut beide Ansichten
styles.css                  Balken und Abstände
```

Die Zahlen selbst kommen aus `local_coursesoverview\progress`.

CI läuft bei jedem Push über `moodle-plugin-ci`, siehe `.github/workflows/ci.yml`.

## Lizenz

GNU GPL v3 oder später.
