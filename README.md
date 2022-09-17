# Skripty pro konverzi DRL a GBR souboru do GRBL
Pouzivam pro KICAD

GBR ma pomezeni na G00-03, nastroje pouze C

Lze nastavit pri bocnim pousunu Z krok, po ktery se frezuje

Lze nastavit korekci rotace, kdy je akce provadena zpetne a je potreba vyrovnat vlozeni predlohy
Napr. vyleptam podklad a potom teprve vrtam, takze po umisteni do CNC se udelam working home, presunu se na znamou stredici znaku, potom zadam skriptu pozici znacky v souboru a skutecnou pozici na CNC. Da se udelat s presnosti 0.1mm, takze je to na vrtani a frezovani PCB bez problemu
napr. znacka v souboru ma 90.75, ale ale pozice na CNC je X88Y77.2, takze zadam skriptu a ten to pootoci

php gbr2grbl.php mkspwc2-Milling.gbr 90,75,88,77.2