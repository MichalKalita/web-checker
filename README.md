Aplikace pro procházení webu a shromažďování informací 
=====

Instalace
-----
**Vyžaduje [Composer](http://getcomposer.org/doc/00-intro.md#installation-nix).**
Uvedené příkazy předpokládají, že jste v kořenové složce projektu.

1. Instalace závislostí přes composer

		composer install

2. Nastavení přístupových práv

		chmod 777 temp/ log/
		chmod +x run.sh

3. Načtení struktury databáze ze souboru `db_struct.sql`
4. Nastavení přístupu k databázi
	V souboru `app/config/config.neon` v sekci `nette/database` nastavte údaje pro přístup k databázi.

Spouštění
----
Nejvýhodnější je aplikaci spouštět přes skript `./run.sh`. 

Můžete použít doplňující parametry:
- `-l <int>` pro nastavení limitu načtených stránek v jednom spuštění, výchozí hodnota je **200**
- `-d <pattern>` výběr domén, které se budou procházet; 
ve formátu stejném jako pro LIKE v SQL; výchozí hodnota je **'%'** (vše)
- `-sd <bool>` výběr, zda-li se mají procházet také subdomény; 
v případě 'false' jsou povoleny pouze subdomény 'www' a '' (nic); výchozí hodnota je **false**

Licence
----

<a rel="license" href="http://creativecommons.org/licenses/by-nc/3.0/cz/"><img alt="Licence Creative Commons" style="border-width:0" src="http://i.creativecommons.org/l/by-nc/3.0/cz/88x31.png" /></a><br />Uvedená práce (dílo), jejímž autorem je <span xmlns:cc="http://creativecommons.org/ns#" property="cc:attributionName">Michal Kalita</span>, podléhá licenci <a rel="license" href="http://creativecommons.org/licenses/by-nc/3.0/cz/">Creative Commons Uveďte autora-Neužívejte komerčně 3.0 Česko </a>.
