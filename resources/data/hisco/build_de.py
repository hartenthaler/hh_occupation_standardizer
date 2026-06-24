# -*- coding: utf-8 -*-
"""
Erzeugt hisco_hierarchy_de.csv und hisco_hierarchy_de_notes.md
aus den englischen HISCO-Quelldateien.
Die englischen Quelldateien werden nicht verändert.
"""

import csv, os, datetime

BASE = os.path.dirname(os.path.abspath(__file__))

# ---------------------------------------------------------------------------
# Übersetzungstabellen
# ---------------------------------------------------------------------------

MAJOR_DE = {
    0: ("Wissenschaftler, Techniker und verwandte Berufe",
        "Beschäftigte dieser Hauptgruppe betreiben Forschung und wenden wissenschaftliche Kenntnisse auf technologische, wirtschaftliche, gesellschaftliche und industrielle Problemstellungen an; sie üben professionelle, technische, künstlerische und verwandte Tätigkeiten in Bereichen wie Natur- und Ingenieurwissenschaften, Rechtswesen, Medizin, Religion, Bildung, Literatur, Kunst, Unterhaltung und Sport aus.",
        ""),
    1: ("Wissenschaftler, Techniker und verwandte Berufe",
        "Beschäftigte dieser Hauptgruppe betreiben Forschung und wenden wissenschaftliche Kenntnisse auf technologische, wirtschaftliche, gesellschaftliche und industrielle Problemstellungen an; sie üben professionelle, technische, künstlerische und verwandte Tätigkeiten in Bereichen wie Natur- und Ingenieurwissenschaften, Rechtswesen, Medizin, Religion, Bildung, Literatur, Kunst, Unterhaltung und Sport aus.",
        "Hauptgruppen 0 und 1 bilden gemeinsam die HISCO-Hauptgruppe 0/1."),
    2: ("Verwaltungs- und Führungskräfte",
        "Beschäftigte dieser Hauptgruppe sind als gewählte oder ernannte Mitglieder nationaler, staatlicher oder kommunaler Regierungen tätig und wirken an der Formulierung staatlicher Politik sowie an der Schaffung und Änderung von Gesetzen und amtlichen Vorschriften mit; ferner gehören hierzu Regierungsbeamte, die die Umsetzung staatlicher Politik organisieren und leiten, sowie Direktoren und Manager, die Tätigkeiten privater oder öffentlicher Unternehmen oder Organisationen planen, koordinieren und steuern.",
        ""),
    3: ("Bürokräfte und verwandte Berufe",
        "Beschäftigte dieser Hauptgruppe setzen Gesetze, Vorschriften und Regelungen zentraler, staatlicher oder kommunaler Behörden um; beaufsichtigen Büro- und Verwaltungsarbeiten sowie Verkehrs- und Kommunikationsbetriebe; erfassen und pflegen Aufzeichnungen über finanzielle und sonstige Geschäftsvorgänge; führen Kassentätigkeiten durch; nehmen mündliche oder schriftliche Aussagen stenografisch, per Schreibmaschine oder mit anderen Mitteln auf; bedienen Büromaschinen sowie Telefon- und Telegrafengeräte; führen Personentransportfahrzeuge; wirken im Postwesen und bei der Briefverteilung mit.",
        ""),
    4: ("Kaufleute und Verkäufer",
        "Beschäftigte dieser Hauptgruppe sind mit dem An- und Verkauf von Waren und Dienstleistungen aller Art befasst oder unmittelbar damit verbunden und betreiben Groß- und Einzelhandelsgeschäfte auf eigene Rechnung.",
        ""),
    5: ("Dienstleistungsberufe",
        "Beschäftigte dieser Hauptgruppe organisieren oder erbringen gastronomische, hauswirtschaftliche, persönliche, ordnungs- und sicherheitsrelevante sowie verwandte Dienstleistungen.",
        ""),
    6: ("Land- und forstwirtschaftliche Berufe, Fischer und Jäger",
        "Beschäftigte dieser Hauptgruppe bewirtschaften Höfe und Betriebe auf eigene Rechnung oder in Gemeinschaft, führen land- und forstwirtschaftliche sowie tierzüchterische Tätigkeiten aus, fangen Fische, jagen und stellen Fallen auf und verrichten damit zusammenhängende Arbeiten.",
        ""),
    7: ("Produktions- und verwandte Berufe, Transportgeräteführer und Hilfsarbeiter",
        "Beschäftigte dieser Hauptgruppe sind mit der Gewinnung von Bodenschätzen, Erdöl und Erdgas sowie deren Aufbereitung befasst oder unmittelbar damit verbunden; ebenso mit Fertigungsprozessen, dem Bau, der Instandhaltung und Reparatur von Straßen, Bauwerken, Maschinen und anderen Erzeugnissen. Einbezogen sind auch Personen, die Materialien handhaben, Transportfahrzeuge und andere Geräte bedienen und körperlich anstrengende Hilfsarbeiten verrichten.",
        "Hauptgruppen 7, 8 und 9 bilden gemeinsam die HISCO-Hauptgruppe 7/8/9."),
    8: ("Produktions- und verwandte Berufe, Transportgeräteführer und Hilfsarbeiter",
        "Beschäftigte dieser Hauptgruppe sind mit der Gewinnung von Bodenschätzen, Erdöl und Erdgas sowie deren Aufbereitung befasst oder unmittelbar damit verbunden; ebenso mit Fertigungsprozessen, dem Bau, der Instandhaltung und Reparatur von Straßen, Bauwerken, Maschinen und anderen Erzeugnissen. Einbezogen sind auch Personen, die Materialien handhaben, Transportfahrzeuge und andere Geräte bedienen und körperlich anstrengende Hilfsarbeiten verrichten.",
        "Hauptgruppen 7, 8 und 9 bilden gemeinsam die HISCO-Hauptgruppe 7/8/9."),
    9: ("Produktions- und verwandte Berufe, Transportgeräteführer und Hilfsarbeiter",
        "Beschäftigte dieser Hauptgruppe sind mit der Gewinnung von Bodenschätzen, Erdöl und Erdgas sowie deren Aufbereitung befasst oder unmittelbar damit verbunden; ebenso mit Fertigungsprozessen, dem Bau, der Instandhaltung und Reparatur von Straßen, Bauwerken, Maschinen und anderen Erzeugnissen. Einbezogen sind auch Personen, die Materialien handhaben, Transportfahrzeuge und andere Geräte bedienen und körperlich anstrengende Hilfsarbeiten verrichten.",
        "Hauptgruppen 7, 8 und 9 bilden gemeinsam die HISCO-Hauptgruppe 7/8/9."),
}

MINOR_DE = {
    1:  ("Physikalische Wissenschaftler und verwandte Techniker",
         "Beschäftigte dieser Untergruppe betreiben reine und angewandte Forschung und entwickeln praktische Anwendungen wissenschaftlicher Erkenntnisse in den Naturwissenschaften oder verrichten verwandte technische Hilfstätigkeiten in Bereichen wie Chemie, Mechanik, Wärmelehre, Optik, Akustik, Elektrizitätslehre, Elektronik, Kernphysik, Geophysik, Geologie, Meteorologie und Astronomie.", ""),
    2:  ("Architekten, Ingenieure und verwandte Techniker",
         "Beschäftigte dieser Untergruppe entwerfen Gebäude und überwachen deren Bau; planen und koordinieren die Stadtentwicklung; planen, gestalten und überwachen Landschaftsgestaltungsmaßnahmen; beraten und überwachen den Bau von Bauingenieurwerken; entwickeln und beraten zu elektrischen, elektronischen, mechanischen, chemischen, Bergbau- und anderen Ingenieurtätigkeiten und übernehmen technische Aufsichtsfunktionen.", ""),
    3:  ("Architekten, Ingenieure und verwandte Techniker",
         "Beschäftigte dieser Untergruppe entwerfen Gebäude und überwachen deren Bau; planen und koordinieren die Stadtentwicklung; planen, gestalten und überwachen Landschaftsgestaltungsmaßnahmen; beraten und überwachen den Bau von Bauingenieurwerken; entwickeln und beraten zu elektrischen, elektronischen, mechanischen, chemischen, Bergbau- und anderen Ingenieurtätigkeiten und übernehmen technische Aufsichtsfunktionen.", "Techniker, Zeichner und Vermessungsberufe (minor_id 3 entspricht major_id 0, Untergruppe 3)."),
    4:  ("Flugzeugführer und Schiffsoffiziere",
         "Beschäftigte dieser Untergruppe führen und navigieren Luftfahrzeuge, erteilen Flugunterricht, inspizieren und überwachen die technischen Geräte von Flugzeugen im Flug, befehligen und navigieren Schiffe und Luftkissenfahrzeuge, leiten und beaufsichtigen Maschinenbetrieb an Bord und koordinieren Schiffsversorgung und Reparaturen im Hafen.", ""),
    5:  ("Biowissenschaftler und verwandte Techniker",
         "Beschäftigte dieser Untergruppe betreiben reine und angewandte Forschung und entwickeln praktische Anwendungen wissenschaftlicher Erkenntnisse in den Lebenswissenschaften, spezialisiert auf Biologie, Botanik, Zoologie, Anatomie, Biochemie, Physiologie, Pharmakologie, Pathologie, Genetik, Ökologie, Agronomie, Forstwirtschaft, Gartenbau oder Fischereikunde.", ""),
    6:  ("Ärzte, Zahnärzte, Tierärzte und verwandte Berufe",
         "Beschäftigte dieser Untergruppe diagnostizieren menschliche und tierische Erkrankungen und behandeln sie medizinisch und chirurgisch; stellen Arzneimittel her und geben sie aus; leisten professionelle und nicht-professionelle Pflegedienste; verschreiben und passen Brillen an; erbringen spezielle medizinisch-therapeutische Leistungen und bedienen Röntgengeräte zu diagnostischen oder therapeutischen Zwecken.", ""),
    7:  ("Ärzte, Zahnärzte, Tierärzte und verwandte Berufe",
         "Beschäftigte dieser Untergruppe diagnostizieren menschliche und tierische Erkrankungen und behandeln sie medizinisch und chirurgisch; stellen Arzneimittel her und geben sie aus; leisten professionelle und nicht-professionelle Pflegedienste; verschreiben und passen Brillen an; erbringen spezielle medizinisch-therapeutische Leistungen und bedienen Röntgengeräte zu diagnostischen oder therapeutischen Zwecken.", "minor_id 7 entspricht major_id 0, deckt Pflege und Hebammenwesen ab."),
    8:  ("Statistiker, Mathematiker, Systemanalytiker und verwandte Techniker",
         "Beschäftigte dieser Untergruppe betreiben Forschung in der Statistik, beraten zu statistischen Methoden, planen und führen Erhebungen durch; betreiben Grundlagenforschung in der Mathematik und beraten zu deren Anwendungen; wenden Mathematik, Statistik und Finanzwissen auf Versicherungs- und Rentensysteme an; analysieren Datenverarbeitungsbedarf und entwickeln automatische Datenverarbeitungssysteme.", ""),
    9:  ("Ökonomen",
         "Beschäftigte dieser Untergruppe betreiben wirtschaftswissenschaftliche Forschung und wenden Prinzipien und Theorien der Volkswirtschaftslehre an, um Lösungen für wirtschaftliche Probleme zu entwickeln.", ""),
    11: ("Buchhalter und Wirtschaftsprüfer",
         "Beschäftigte dieser Untergruppe planen und leiten das Rechnungswesen, beraten in Buchführungsfragen und planen sowie führen Finanzprüfungen durch.", ""),
    12: ("Juristen",
         "Beschäftigte dieser Untergruppe vertreten Parteien vor Gericht, führen Strafverfolgungen durch, leiten Gerichtsverfahren und fällen Urteile, beraten in Rechtsfragen, erstellen Rechtsdokumente, entwerfen Gesetze und erfüllen andere juristische Aufgaben.", ""),
    13: ("Lehrkräfte",
         "Beschäftigte dieser Untergruppe erteilen Unterricht und Privatstunden an Hochschulen, Sekundar-, Primar- und Vorschulen, unterrichten Personen mit Behinderungen, forschen zu Unterrichtsmethoden und Lernmitteln, organisieren und leiten Lehrtätigkeiten an Schulen.", ""),
    14: ("Geistliche und Religionsdiener",
         "Beschäftigte dieser Untergruppe üben religiöse Dienste aus, spenden geistliche Führung, verbreiten Glaubenslehren, erfüllen andere mit der Ausübung von Religionen verbundene Aufgaben und bemühen sich, Erkrankungen durch Glaubensheilung zu lindern.", ""),
    15: ("Autoren, Journalisten und verwandte Schreibberufe",
         "Beschäftigte dieser Untergruppe verfassen literarische Werke für die Veröffentlichung oder Aufführung, schreiben Kritiken über literarische, künstlerische und musikalische Werke sowie Aufführungen und verfassen sonstiges schriftliches Material zur Information, Unterhaltung oder Beeinflussung der Öffentlichkeit.", ""),
    16: ("Bildhauer, Maler, Fotografen und verwandte bildende Künstler",
         "Beschäftigte dieser Untergruppe schaffen und gestalten künstlerische Werke durch Bildhauen, Malen, Zeichnen, Gravieren und Radieren; setzen künstlerische Ausdrucksmittel für illustrative, dekorative und werbliche Zwecke ein; fotografieren und leiten die Arbeit an Film- und Fernsehkameras.", ""),
    17: ("Komponisten und darstellende Künstler",
         "Beschäftigte dieser Untergruppe komponieren, arrangieren, dirigieren und führen Musikwerke und Tänze auf; produzieren, inszenieren und spielen in Theater-, Film- und Rundfunkproduktionen; führen unterhaltsame, verblüffende und spektakuläre Darbietungen im Zirkus und anderen Veranstaltungen auf.", ""),
    18: ("Sportler, Sportlerinnen und verwandte Berufe",
         "Beschäftigte dieser Untergruppe nehmen berufsmäßig an öffentlichen Sportwettkämpfen teil und leiten deren Durchführung, trainieren Sportlerinnen und Sportler und unterrichten Personen in körperlicher Fitness.", ""),
    19: ("Wissenschaftler, Techniker und verwandte Berufe anderweitig nicht klassifiziert",
         "Beschäftigte dieser Untergruppe üben professionelle und technische Tätigkeiten aus, die nicht anderweitig klassifiziert sind.", ""),
    20: ("Gesetzgebende Beamte und Regierungsverwalter",
         "Beschäftigte dieser Untergruppe entscheiden über oder wirken an der Formulierung staatlicher Politik mit, schaffen, ändern oder heben Gesetze und amtliche Vorschriften auf und leiten die Umsetzung staatlicher Politik durch Regierungsbehörden auf nationaler, staatlicher, provinzieller oder kommunaler Ebene.", ""),
    21: ("Unternehmensleiter und -manager",
         "Beschäftigte dieser Untergruppe planen, organisieren, koordinieren und leiten öffentliche und private Industrie-, Handels-, Versorgungs-, Verkehrs-, Kommunikations- und andere Unternehmen, Betriebe und Organisationen oder einzelne Abteilungen davon.", ""),
    22: ("Aufsichtskräfte, Vorarbeiter und Inspektoren",
         "Beschäftigte dieser Untergruppe organisieren, beaufsichtigen und kontrollieren in Industrie- und Handelsbetrieben, öffentlichen und privaten Organisationen und Institutionen die täglichen Aufgaben und Tätigkeiten untergeordneter Mitarbeiter.", ""),
    30: ("Bürokräfte und verwandte Berufe, Spezialisierung unbekannt",
         "Beschäftigte dieser Untergruppe können beliebige (aber nicht alle) beruflichen Tätigkeiten der Untergruppen 3-1 bis 3-9 ausüben.", ""),
    31: ("Regierungsbeamte (ausführende Ebene)",
         "Beschäftigte dieser Untergruppe setzen Entscheidungen der Regierungspolitik um und vollziehen Gesetze, Regeln und Vorschriften unter der Leitung von Regierungsverwaltern.", ""),
    32: ("Stenografen, Schreibkräfte und Lochkarten-/Lochstreifenbediener",
         "Beschäftigte dieser Untergruppe nehmen mündliches oder schriftliches Material stenografisch und per Schreibmaschine auf und bedienen Lochkarten- oder Lochstreifenmaschinen.", ""),
    33: ("Buchhalter, Kassierer und verwandte Berufe",
         "Beschäftigte dieser Untergruppe führen Aufzeichnungen über Geschäftsvorgänge, verwalten Bargeld im Auftrag einer Organisation und ihrer Kunden, berechnen Kosten und Löhne und erfüllen andere buchhalterische und kaufmännische Aufgaben.", ""),
    34: ("Rechenmaschinenbediener",
         "Beschäftigte dieser Untergruppe bedienen Buchhalter-, Rechen- und automatische Datenverarbeitungsmaschinen.", ""),
    36: ("Fahrzeugschaffner",
         "Beschäftigte dieser Untergruppe betreuen Züge, Busse und andere öffentliche Verkehrsmittel während der Fahrt und sorgen für die Sicherheit der Fahrgäste.", ""),
    37: ("Postzusteller und Briefverteiler",
         "Beschäftigte dieser Untergruppe sortieren, registrieren, zustellen und führen andere Tätigkeiten im Zusammenhang mit der Verteilung von Post und der Übermittlung von Nachrichten aus.", ""),
    38: ("Telefon- und Telegrafenbediener",
         "Beschäftigte dieser Untergruppe übermitteln und empfangen Nachrichten durch Bedienung von Telekommunikationsgeräten an Land, auf See und in Luftfahrzeugen.", ""),
    39: ("Bürokräfte anderweitig nicht klassifiziert",
         "Beschäftigte dieser Untergruppe erfüllen verschiedene kaufmännische und bürotechnische Aufgaben, die anderweitig nicht klassifiziert sind.", ""),
    41: ("Selbstständige Kaufleute (Groß- und Einzelhandel)",
         "Beschäftigte dieser Untergruppe betreiben Groß- und Einzelhandelsgeschäfte auf eigene Rechnung oder in Gemeinschaft und sind mit dem An- und Verkauf von Waren befasst.", ""),
    42: ("Einkäufer",
         "Beschäftigte dieser Untergruppe kaufen Waren für den Wiederverkauf oder den Eigenbedarf im Auftrag von Groß-, Einzel-, Industrie- oder anderen Unternehmen und Einrichtungen.", ""),
    43: ("Technische Handelsvertreter, Reisevertreter und Herstelleragenten",
         "Beschäftigte dieser Untergruppe üben spezialisierte technische Verkaufstätigkeiten aus, die Kenntnisse über Zusammensetzung, Verwendung und Wartung der verkauften Güter oder Ausrüstungen erfordern; erteilen technische Beratung und handeln als Vermittler für den Großhandelsverkauf.", ""),
    44: ("Versicherungs-, Immobilien-, Wertpapier- und Unternehmensdienstleistungsverkäufer sowie Auktionatoren",
         "Beschäftigte dieser Untergruppe verkaufen Versicherungen, Immobilien, Wertpapiere, Unternehmens- und Werbedienstleistungen oder versteigern Güter und Liegenschaften.", ""),
    45: ("Verkäufer, Ladengehilfen und verwandte Berufe",
         "Beschäftigte dieser Untergruppe verkaufen und demonstrieren Waren in Groß- und Einzelhandelsbetrieben oder auf der Straße, suchen Aufträge an der Haustür oder im Straßenhandel und erfüllen verwandte Verkaufsaufgaben.", ""),
    49: ("Kaufleute und Verkäufer anderweitig nicht klassifiziert",
         "Beschäftigte dieser Untergruppe erfüllen verschiedene Verkaufsaufgaben, die anderweitig nicht klassifiziert sind.", ""),
    51: ("Selbstständige (Gastronomie, Beherbergung und Freizeitdienstleistungen)",
         "Beschäftigte dieser Untergruppe betreiben Gastronomie-, Beherbergungs- und Freizeitdienstleistungsbetriebe auf eigene Rechnung oder in Gemeinschaft.", ""),
    53: ("Köche, Kellner, Barkeeper und verwandte Berufe",
         "Beschäftigte dieser Untergruppe überwachen und führen verschiedene Arbeiten zur Zubereitung und Ausgabe von Speisen und Getränken aus.", ""),
    54: ("Hauswirtschaftliche Dienstleistungsberufe anderweitig nicht klassifiziert",
         "Beschäftigte dieser Untergruppe erbringen persönliche und hauswirtschaftliche Dienstleistungen in Privathaushalten, Hotels und an Bord von Schiffen, in öffentlichen Verkehrsmitteln und anderen Einrichtungen.", ""),
    55: ("Hausmeister, Reinigungskräfte und verwandte Berufe",
         "Beschäftigte dieser Untergruppe betreuen und reinigen Fenster, Gebäudeinnenräume, Einrichtungsgegenstände und Mobiliar.", ""),
    56: ("Wäscher, chemische Reiniger und Bügler",
         "Beschäftigte dieser Untergruppe waschen, reinigen, bügeln und flicken Bekleidung, Textilstoffe und ähnliche Gegenstände.", ""),
    57: ("Friseure, Barbiere, Kosmetiker und verwandte Berufe",
         "Beschäftigte dieser Untergruppe schneiden und frisieren Haare, tragen Kosmetik und Make-up auf und erfüllen verwandte Tätigkeiten zur Verbesserung des äußeren Erscheinungsbildes.", ""),
    58: ("Ordnungs- und Sicherheitsberufe",
         "Beschäftigte dieser Untergruppe schützen Personen und Eigentum vor Gefahren und setzen Recht und Ordnung durch.", ""),
    59: ("Dienstleistungsberufe anderweitig nicht klassifiziert",
         "Beschäftigte dieser Untergruppe erfüllen verschiedene Dienstleistungsaufgaben, die anderweitig nicht klassifiziert sind.", ""),
    61: ("Landwirte (Selbstständige)",
         "Beschäftigte dieser Untergruppe bewirtschaften gemischte oder spezialisierte landwirtschaftliche Betriebe und Tierhaltungsbetriebe auf eigene Rechnung oder in Gemeinschaft.", "Der englische Begriff 'Farmers' bezeichnet selbstständige Hofbewirtschafter und entspricht nicht dem deutschen Alltagsbegriff 'Bauer'; die Übersetzung folgt dem Funktionsmerkmal."),
    62: ("Land- und tierzüchterische Arbeitskräfte",
         "Beschäftigte dieser Untergruppe erfüllen verschiedene Aufgaben im Feldfrucht- und Gemüseanbau, in der Baumzucht und Blumenzucht, in der Vieh- und Geflügelzucht, bei der Bedienung landwirtschaftlicher Maschinen sowie verwandte land- und tierzüchterische Tätigkeiten.", ""),
    63: ("Forstarbeiter",
         "Beschäftigte dieser Untergruppe beaufsichtigen und führen Tätigkeiten in der Kultivierung, Erhaltung und Nutzung von Wäldern durch.", ""),
    64: ("Fischer, Jäger und verwandte Berufe",
         "Beschäftigte dieser Untergruppe fangen und sammeln Fische, jagen und stellen Fallen auf für Tiere und erfüllen verwandte Tätigkeiten.", ""),
    71: ("Bergarbeiter, Steinbrecher, Bohrarbeiter und verwandte Berufe",
         "Beschäftigte dieser Untergruppe gewinnen feste Mineralien aus unterirdischen oder oberirdischen Bergwerken und Steinbrüchen, bereiten die geförderten Mineralien für Verteilung oder Weiterverarbeitung auf und errichten sowie betreiben Bohreinrichtungen.", ""),
    72: ("Metallbearbeiter",
         "Beschäftigte dieser Untergruppe bedienen Schmelz-, Konvertier-, Raffinations-, Schmelz- und Wiedererwärmungsöfen; betreiben Walzwerke; gießen Metall in Formen und bedienen Metallgussmaschinen; stellen Sandformen und Kerne für den Metallguss her; verändern physikalische Eigenschaften von Metallgegenständen durch Wärmebehandlung und chemische Behandlung; ziehen und strangpressen Metalle zu Draht, Rohren und ähnlichen Erzeugnissen; betreiben Beschichtungsanlagen.", ""),
    73: ("Holzaufbereiter und Papiermacher",
         "Beschäftigte dieser Untergruppe trocknen und konservieren Holz; bedienen Maschinen zum Sägen, Furnieren und Sperrholzherstellen; bereiten Zellstoff für die Papierherstellung auf; stellen Papier von Hand oder mit Maschinen her.", ""),
    74: ("Chemische Verfahrensarbeiter und verwandte Berufe",
         "Beschäftigte dieser Untergruppe zerkleinern, mahlen, mischen, kochen, rösten, filtern, trennen, destillieren, raffinieren und verarbeiten anderweitig Chemikalien und andere Materialien in chemischen Prozessen.", ""),
    75: ("Spinner, Weber, Wirker, Färber und verwandte Berufe",
         "Beschäftigte dieser Untergruppe bereiten natürliche Textilfasern für das Spinnen vor; spinnen, zwirnen und wickeln Fäden und Garne; richten Web- und Wirkmaschinen ein und stellen Jacquardkarten her; weben Stoffe; wirken Kleidungsstücke und Gewebe; bleichen, färben und veredeln Textilerzeugnisse.", ""),
    76: ("Gerber, Fellbereiter und Pelzzurichter",
         "Beschäftigte dieser Untergruppe bereiten Häute, Felle und Pelze für die Herstellung von Leder- und Pelzwaren vor.", ""),
    77: ("Lebensmittel- und Getränkehersteller",
         "Beschäftigte dieser Untergruppe stellen Nahrungsmittel und Getränke aller Art für den menschlichen und tierischen Verzehr her.", ""),
    78: ("Tabakaufbereiter und Tabakwarenhersteller",
         "Beschäftigte dieser Untergruppe bereiten Tabakblätter auf und stellen Zigarren, Zigaretten und andere Tabakwaren her.", ""),
    79: ("Schneider, Näher, Polsterer und verwandte Berufe",
         "Beschäftigte dieser Untergruppe fertigen oder wirken an der Herstellung von Kleidungsstücken, Hüten, Handschuhen und anderen Artikeln aus Textilien, Pelz, Leder und ähnlichen Materialien; polstern Möbel und Fahrzeuginnenräume; nähen Textilerzeugnisse für Möbel und Dekoration.", ""),
    80: ("Schuhmacher und Lederwaren­hersteller",
         "Beschäftigte dieser Untergruppe fertigen und reparieren Schuhwerk hauptsächlich aus Leder und stellen Sättel, Geschirr und verschiedene andere Erzeugnisse aus Leder oder ähnlichen Materialien her.", ""),
    81: ("Schreiner, Tischler und verwandte Holzbearbeiter",
         "Beschäftigte dieser Untergruppe fertigen und reparieren Holzmöbel, hochwertige Holzeinbauten und ähnliche Gegenstände; richten Präzisions-Holzbearbeitungsmaschinen ein und bedienen sie; führen andere nicht anderweitig klassifizierte Holzbearbeitungsarbeiten aus.", ""),
    82: ("Steinmetze und Steinhauer",
         "Beschäftigte dieser Untergruppe hauen und formen Stein für Bau-, Denkmalpflege- und Schmuckzwecke.", ""),
    83: ("Schmiede, Werkzeugmacher und Werkzeugmaschinenbediener",
         "Beschäftigte dieser Untergruppe hämmern und schmieden Metall von Hand oder mit Maschinen; fertigen Werkzeuge, Matrizen, Vorrichtungen und andere Metallerzeugnisse; richten Zerspanungsmaschinen ein; bedienen eingerichtete Maschinen für Serienarbeit; schleifen und schärfen Werkzeuge.", ""),
    84: ("Maschinenschlosser, Maschinenmonteure und Feinmechaniker (außer Elektriker)",
         "Beschäftigte dieser Untergruppe montieren, installieren, warten und reparieren verschiedene Maschinentypen, Metallerzeugnisse, Motoren und mechanische Ausrüstungen sowie Uhren, Chronometer und Präzisionsinstrumente (außer elektrische).", ""),
    85: ("Elektroinstallateure und verwandte Elektro- und Elektronikmonteure",
         "Beschäftigte dieser Untergruppe montieren, installieren, warten und reparieren Elektro- und Elektronikgeräte wie Elektromotoren, Generatoren, Instrumente, Signalüberträger und -empfänger; installieren und warten Starkstromkabel, Telefon- und Telegrafenleitungen.", ""),
    86: ("Rundfunk- und Tontechnikbediener und Filmvorführer",
         "Beschäftigte dieser Untergruppe bedienen Radio- und Fernsehsendetechnik, installieren und betreiben Tonaufnahme- und Verstärkeranlagen und führen Kinovorführungen durch.", ""),
    87: ("Klempner, Schweißer, Blechner und Stahlbauer",
         "Beschäftigte dieser Untergruppe montieren, verlegen, installieren und reparieren Sanitärinstallationen, Rohrsysteme und Rohrleitungsanlagen; formen und schneiden Metallteile mit Flamme, Lichtbogen oder anderen Wärmequellen; fertigen und reparieren Blechwaren; formen und montieren schwere Stahlkonstruktionen.", ""),
    88: ("Juweliere und Edelmetallfacharbeiter",
         "Beschäftigte dieser Untergruppe fertigen und reparieren Schmuck und Edelmetallwaren, formen und fassen Edelsteine und gravieren Schmuck- und Edelmetallerzeugnisse.", ""),
    89: ("Glasverarbeiter, Töpfer und verwandte Berufe",
         "Beschäftigte dieser Untergruppe blasen, formen, pressen und walzen Formen aus Glasschmelze; schneiden, schleifen und veredeln Glas; formen Keramikerzeugnisse; betreiben Schmelzöfen und Brennöfen; gravieren, ätzen, bemalen und dekorieren Glas- und Keramikerzeugnisse.", ""),
    90: ("Gummi- und Kunststoffverarbeiter",
         "Beschäftigte dieser Untergruppe verarbeiten Rohgummi und Gummimischungen und stellen Erzeugnisse aus Natur- und Synthesekautschuk sowie Kunststoffen durch Strangpressen, Formen, Laminieren und Vulkanisieren her.", ""),
    91: ("Papier- und Kartonwarenerzeuger",
         "Beschäftigte dieser Untergruppe stellen Schachteln, Umschläge, Beutel und andere Erzeugnisse aus Papier, Pappe, Karton, Zellophan und ähnlichen Materialien her.", ""),
    92: ("Drucker und verwandte Berufe",
         "Beschäftigte dieser Untergruppe setzen Schrift, gießen und gravieren Druckplatten und bedienen Druckmaschinen; binden Bücher; entwickeln und vervielfältigen fotografische Still- und Filmaufnahmen; führen verwandte Tätigkeiten aus.", ""),
    93: ("Maler (Bau)",
         "Beschäftigte dieser Untergruppe bereiten Baukonstruktionen für den Anstrich vor und tragen dekorative und schützende Beschichtungen auf Gebäude, Schiffe, Kraftfahrzeuge und Artikel aus Holz, Metall, Textilien und anderen Materialien auf (außer Glas und Keramik).", ""),
    94: ("Produktions- und verwandte Berufe anderweitig nicht klassifiziert",
         "Beschäftigte dieser Gruppe sind Produktions- und verwandte Arbeitskräfte, die in keiner anderen Untergruppe klassifiziert sind. Einbezogen sind Handwerker und spezialisierte Fachkräfte, die besondere Techniken, Werkzeuge oder Maschinen einsetzen sowie Fähigkeiten und Erfahrung in der Bearbeitung bestimmter Materialien besitzen.", ""),
    95: ("Maurer, Zimmerleute und andere Bauarbeiter",
         "Beschäftigte dieser Untergruppe errichten und reparieren Gebäude und andere Bauwerke; mauern, setzen Steine und Fliesen; errichten Stahlbetonkonstruktionen; decken Dachstuhlkonstruktionen; fertigen, errichten, montieren und reparieren Holzkonstruktionen und Holzeinbauten; verputzen und isolieren Gebäude; verglasern Gebäude und Fahrzeuge.", ""),
    96: ("Bediener stationärer Maschinen und Anlagen",
         "Beschäftigte dieser Untergruppe bedienen Anlagen zur Stromerzeugung und steuern deren Verteilung; bedienen und warten stationäre Maschinen und zugehörige Anlagen wie Dampfkessel, Luft- und Gaskompressoren, Pumpen, Kälteanlagen, Heizungs- und Lüftungssysteme sowie Wasser- und Abfallaufbereitungsanlagen.", ""),
    97: ("Umschlag- und Transportgeräteführer, Hafenarbeiter und Lageristen",
         "Beschäftigte dieser Untergruppe führen Umschlagarbeiten durch, bedienen Kräne und andere Hebezeuge; betreiben Erdbewegungs- und Straßenbaumaschinen; bedienen Spezialfahrzeuge zum Heben, Transportieren, Kippen und Stapeln von Materialien.", ""),
    98: ("Transportgeräteführer",
         "Beschäftigte dieser Untergruppe führen Fahrzeuge und verrichten damit zusammenhängende Tätigkeiten im Transport von Personen und Frachten auf Wasserfahrzeugen, im Landverkehr und mit Lasttieren.", ""),
    99: ("Sonstige Arbeitskräfte anderweitig nicht klassifiziert",
         "Beschäftigte dieser Untergruppe verrichten einfache und routinemäßige manuelle Tätigkeiten, die hauptsächlich körperliche Anstrengung erfordern und wenig oder keine Vorerfahrung voraussetzen, und die nicht von anderweitig klassifizierten Arbeitskräften ausgeführt werden.", ""),
}

UNIT_DE = {
    11:  ("Chemiker", "Beschäftigte dieser Berufsgruppe betreiben Forschung in Bereichen wie organische, anorganische, physikalische und analytische Chemie; führen Grundlagen- und angewandte Forschung durch; führen chemische Tests und Analysen zur Prozess- und Qualitätskontrolle durch und entwickeln analytische Methoden.", ""),
    12:  ("Physiker", "Beschäftigte dieser Berufsgruppe betreiben Forschung zu physikalischen Phänomenen in Bereichen wie Mechanik, Wärmelehre, Optik, Akustik, Elektrizität und Magnetismus, Elektronik und Kernphysik.", ""),
    13:  ("Physikalische Wissenschaftler anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe betreiben Forschung und entwickeln praktische Anwendungen in Bereichen der Naturwissenschaften, die nicht anderweitig klassifiziert sind, einschließlich Geologie, Geophysik, Meteorologie und Astronomie.", ""),
    14:  ("Techniker in den Naturwissenschaften", "Beschäftigte dieser Berufsgruppe erfüllen technische Hilfstätigkeiten unter Anleitung und Aufsicht von Naturwissenschaftlern in Forschung und Entwicklung in den physikalischen Wissenschaften.", ""),
    20:  ("Ingenieure, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe können beliebige, aber nicht alle Ingenieurtätigkeiten der Berufsgruppen 0-22 bis 0-29 ausüben.", ""),
    21:  ("Architekten und Stadtplaner", "Beschäftigte dieser Berufsgruppe entwerfen Gebäude und überwachen deren Bau, planen die Stadtentwicklung und gestalten sowie überwachen Landschaftsgestaltungsprojekte.", ""),
    22:  ("Bauingenieure", "Beschäftigte dieser Berufsgruppe forschen zu bauingenieurwissenschaftlichen Problemen, entwerfen und überwachen den Bau von Brücken, Dämmen, Hafenanlagen, Straßen, Flughäfen, Eisenbahnen, Entsorgungsanlagen und Industriebauten.", ""),
    23:  ("Elektro- und Elektronikmaschinenbauingenieure", "Beschäftigte dieser Berufsgruppe forschen zu elektrotechnischen und elektronischen Problemen, entwerfen und beraten zu Systemen und Geräten und überwachen Entwicklung, Bau, Installation und Betrieb.", ""),
    24:  ("Maschinenbauingenieure", "Beschäftigte dieser Berufsgruppe forschen zu mechanisch wirkenden Anlagen und Geräten, beraten zu deren Konstruktion und überwachen Entwicklung, Fertigung und Instandhaltung.", ""),
    25:  ("Chemieingenieure", "Beschäftigte dieser Berufsgruppe forschen zu chemischen und physikalischen Umwandlungsprozessen, entwickeln Produktionsverfahren und überwachen den Bau und Betrieb entsprechender Anlagen.", ""),
    26:  ("Metallurgen", "Beschäftigte dieser Berufsgruppe beraten zu metallurgischen Problemen, entwickeln und steuern Prozesse zur Metallgewinnung aus Erzen, untersuchen Metalleigenschaften und entwickeln neue Legierungen.", ""),
    27:  ("Bergbauingenieure", "Beschäftigte dieser Berufsgruppe forschen zu bergbautechnischen Problemen, überwachen Prospektion und Förderung von Mineralien und beraten zur Aufbereitung geförderter Rohstoffe.", ""),
    28:  ("Wirtschaftsingenieure", "Beschäftigte dieser Berufsgruppe analysieren und beraten zur effizienten, sicheren und wirtschaftlichen Nutzung von Personal, Materialien und Ausrüstungen, führen Zeitstudien durch und entwickeln Arbeitsbewertungsverfahren.", ""),
    29:  ("Ingenieure anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Ingenieurtätigkeiten, die nicht anderweitig klassifiziert sind, darunter in der Lebensmittel- und Getränketechnologie, Agrartechnik und Verkehrsplanung.", ""),
    30:  ("Vermessungsingenieure und Kartografen", "Beschäftigte dieser Berufsgruppe vermessen die Erdoberfläche und unterirdische Bereiche und erstellen Karten und Pläne.", ""),
    31:  ("Zeichner und Kartografen", "Beschäftigte dieser Berufsgruppe erstellen technische Zeichnungen und Karten und übertragen Zeichnungen auf Druckplatten.", ""),
    32:  ("Techniker, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe können beliebige, aber nicht alle Tätigkeiten der Berufsgruppen 0-14 und 0-33 bis 0-39 ausüben.", ""),
    33:  ("Bautechniker", "Beschäftigte dieser Berufsgruppe erfüllen technische Aufgaben unter Anleitung von Bauingenieuren, Architekten oder Vermessungsingenieuren, einschließlich Mengen- und Kostenermittlung sowie Bauüberwachung.", ""),
    34:  ("Elektro- und Elektroniktechniker", "Beschäftigte dieser Berufsgruppe erfüllen technische Aufgaben unter Anleitung von Elektro- oder Elektronikingenieuren in Entwicklung, Bau, Installation und Wartung von Elektro- und Elektroniksystemen.", ""),
    35:  ("Maschinenbautechniker", "Beschäftigte dieser Berufsgruppe erfüllen technische Aufgaben unter Anleitung von Maschinenbauingenieuren in Entwicklung, Fertigung, Bau, Installation und Wartung mechanischer Anlagen und Geräte.", ""),
    36:  ("Chemietechniker", "Beschäftigte dieser Berufsgruppe erfüllen technische Aufgaben unter Anleitung von Chemieingenieuren in der Entwicklung chemischer Verfahren und im Bau, Betrieb und der Wartung chemischer Anlagen.", ""),
    37:  ("Metallurgietechniker", "Beschäftigte dieser Berufsgruppe erfüllen technische Aufgaben unter Anleitung von Metallurgen in der Entwicklung und Kontrolle von Prozessen zur Metallgewinnung und -verarbeitung.", ""),
    38:  ("Bergbautechniker", "Beschäftigte dieser Berufsgruppe erfüllen technische Aufgaben unter Anleitung von Bergbauingenieuren bei der Förderung und Aufbereitung von Mineralien.", ""),
    39:  ("Ingenieurtechniker anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen technische Tätigkeiten im Ingenieurwesen, die nicht anderweitig klassifiziert sind.", ""),
    41:  ("Flugzeugführer, Flugnavigationsoffiziere und Bordingenieure", "Beschäftigte dieser Berufsgruppe fliegen Luftfahrzeuge, navigieren diese im Flug, inspizieren und überwachen die technischen Geräte während des Fluges und erteilen Flugunterricht.", ""),
    42:  ("Schiffskapitäne, Decksoffiziere und Lotsen", "Beschäftigte dieser Berufsgruppe führen und navigieren Schiffe und Luftkissenfahrzeuge und koordinieren die Schiffsversorgung im Hafen.", ""),
    43:  ("Schiffsmaschinisten", "Beschäftigte dieser Berufsgruppe planen, koordinieren, leiten und beteiligen sich an Betrieb, Wartung und Reparatur mechanischer und zugehöriger Ausrüstungen an Bord oder von der Küste aus.", ""),
    51:  ("Biologen, Zoologen und verwandte Wissenschaftler", "Beschäftigte dieser Berufsgruppe betreiben Grundlagenforschung über alle Lebensformen und angewandte Forschung zur Entwicklung praktischer Anwendungen in Medizin, Landwirtschaft und Forstwirtschaft.", ""),
    52:  ("Bakteriologen, Pharmakologen und verwandte Wissenschaftler", "Beschäftigte dieser Berufsgruppe untersuchen Aufbau, Zusammensetzung und Lebensvorgänge von Menschen, Tieren und Mikroorganismen und wenden diese Erkenntnisse für medizinische, landwirtschaftliche und industrielle Zwecke an.", ""),
    53:  ("Agronomen und verwandte Wissenschaftler", "Beschäftigte dieser Berufsgruppe forschen zu Feldfruchtanbau, Gartenbau, Forstwirtschaft und Bodenkunde und entwickeln verbesserte Anbau-, Kultur- und Ernte­methoden.", ""),
    54:  ("Biowissenschaftliche Techniker", "Beschäftigte dieser Berufsgruppe erfüllen technische Hilfstätigkeiten unter Anleitung von Biowissenschaftlern in Forschung und Entwicklung in den Lebenswissenschaften.", ""),
    61:  ("Ärzte", "Beschäftigte dieser Berufsgruppe wenden medizinisches Wissen auf Prävention, Diagnose und Behandlung menschlicher Erkrankungen an.", ""),
    62:  ("Medizinische Hilfskräfte", "Beschäftigte dieser Berufsgruppe erfüllen im Rahmen eines öffentlichen Gesundheitsdienstes oder einer Einrichtung begrenzte diagnostische, präventive und kurative Aufgaben.", ""),
    63:  ("Zahnärzte", "Beschäftigte dieser Berufsgruppe wenden medizinisches Wissen auf Prävention, Diagnose und Behandlung menschlicher Zahn- und Munderkrankungen an.", ""),
    64:  ("Zahntechnische Hilfskräfte", "Beschäftigte dieser Berufsgruppe erfüllen begrenzte diagnostische, kurative und präventive zahnärztliche Aufgaben im öffentlichen Zahnarztdienst.", ""),
    65:  ("Tierärzte", "Beschäftigte dieser Berufsgruppe diagnostizieren und behandeln Krankheiten, Verletzungen und andere Erkrankungen von Tieren medizinisch und chirurgisch.", ""),
    66:  ("Tierärztliche Hilfskräfte", "Beschäftigte dieser Berufsgruppe erfüllen begrenzte diagnostische, präventive und kurative tierärztliche Aufgaben.", ""),
    67:  ("Apotheker", "Beschäftigte dieser Berufsgruppe stellen Arzneimittel und verwandte Präparate nach ärztlichen Rezepten oder festgelegten Formeln her und geben sie aus.", ""),
    68:  ("Pharmazeutische Hilfskräfte", "Beschäftigte dieser Berufsgruppe unterstützen Apotheker bei der Herstellung und Ausgabe von Arzneimitteln.", ""),
    69:  ("Diätassistenten und Ernährungsberater im öffentlichen Gesundheitswesen", "Beschäftigte dieser Berufsgruppe planen und überwachen die Zusammenstellung von Diäten für Einzelpersonen oder Gruppen und beraten zu Ernährungsprogrammen.", ""),
    71:  ("Diplomkrankenpfleger und -pflegerinnen", "Beschäftigte dieser Berufsgruppe erbringen professionelle Pflegeleistungen in Krankenhäusern, Kliniken, Schulen, Betrieben und Privathaushalten.", ""),
    72:  ("Pflegepersonal anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erbringen einfache Pflegeleistungen für Patienten, in der Regel unter Aufsicht eines Arztes oder einer diplomierten Pflegefachkraft.", ""),
    73:  ("Diplomhebammen", "Beschäftigte dieser Berufsgruppe erbringen professionelle Geburtshilfeleistungen in Krankenhäusern, Kliniken und andernorts.", ""),
    74:  ("Hebammenpersonal anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe leisten praktische Geburtshilfe in der Regel in Privathäusern ohne ärztliche Begleitung.", ""),
    75:  ("Augenoptiker und Optiker", "Beschäftigte dieser Berufsgruppe untersuchen Augen, verschreiben und passen Brillen an.", ""),
    76:  ("Physiotherapeuten und Ergotherapeuten", "Beschäftigte dieser Berufsgruppe erbringen spezielle medizinisch-therapeutische Leistungen durch physikalische Mittel oder gezielte Aktivitäten.", ""),
    77:  ("Radiologisch-technische Assistenten", "Beschäftigte dieser Berufsgruppe bedienen Röntgengeräte für medizinische Diagnose oder Therapie.", ""),
    79:  ("Medizinisches, zahnärztliches, tierärztliches und verwandtes Personal anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erbringen medizinische, zahnärztliche, tierärztliche und verwandte Fachdienstleistungen, die nicht anderweitig klassifiziert sind.", ""),
    81:  ("Statistiker", "Beschäftigte dieser Berufsgruppe forschen zu den mathematischen Grundlagen der Statistik, entwickeln statistische Methoden, beraten zur praktischen Anwendung und führen statistische Erhebungen durch.", ""),
    82:  ("Mathematiker und Versicherungsmathematiker", "Beschäftigte dieser Berufsgruppe betreiben Grundlagenforschung in der Mathematik, beraten zu deren praktischen Anwendungen und analysieren versicherungsmathematische Fragestellungen.", ""),
    83:  ("Systemanalytiker", "Beschäftigte dieser Berufsgruppe analysieren den Datenverarbeitungsbedarf von Unternehmen und Organisationen, beraten zu automatisierten Datenverarbeitungssystemen und führen diese ein.", ""),
    84:  ("Statistisch-mathematische Techniker", "Beschäftigte dieser Berufsgruppe erstellen Programme zur automatischen Datenverarbeitung und erfüllen andere technische Aufgaben unter Anleitung von Statistikern, Mathematikern und Versicherungsmathematikern.", ""),
    90:  ("Ökonomen", "Beschäftigte dieser Berufsgruppe betreiben wirtschaftswissenschaftliche Forschung und wenden Prinzipien und Theorien der Volkswirtschaftslehre auf Probleme der Produktion, Verteilung und des Austauschs von Gütern und Dienstleistungen an.", ""),
    110: ("Buchhalter und Wirtschaftsprüfer", "Beschäftigte dieser Berufsgruppe planen und leiten das Rechnungswesen, beraten zu Buchführungsfragen und führen Prüfungen von Jahresabschlüssen durch.", ""),
    120: ("Juristen, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe können beliebige, aber nicht alle juristischen Tätigkeiten der Berufsgruppen 1-21 bis 1-29 ausüben.", ""),
    121: ("Rechtsanwälte", "Beschäftigte dieser Berufsgruppe vertreten Parteien vor Gericht oder führen Strafverfolgungen durch.", ""),
    122: ("Richter", "Beschäftigte dieser Berufsgruppe leiten Gerichtsverfahren und fällen Urteile.", ""),
    123: ("Notare", "Beschäftigte dieser Berufsgruppe errichten, beurkunden und beglaubigen rechtserhebliche Dokumente und Urkunden.", ""),
    124: ("Rechtsbeistände und Solicitors", "Beschäftigte dieser Berufsgruppe beraten Mandanten in persönlichen, geschäftlichen und verwaltungsrechtlichen Rechtsfragen.", "Der englische Begriff 'Solicitors' ist ein britisch-rechtlicher Berufstitel; keine direkte deutsche Entsprechung; Übersetzung als 'Rechtsbeistände' gewählt."),
    129: ("Juristen anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen juristische Tätigkeiten, die nicht anderweitig klassifiziert sind.", ""),
    130: ("Lehrkräfte, Unterrichtsstufe und Fach unbekannt", "Beschäftigte dieser Berufsgruppe können auf beliebigen, aber nicht allen Schulstufen unterrichten.", ""),
    131: ("Hochschul- und Universitätslehrkräfte", "Beschäftigte dieser Berufsgruppe halten Lehrveranstaltungen an Hochschulen, betreuen Studierende in Forschungsarbeiten und erteilen Privatunterricht auf Hochschulniveau.", ""),
    132: ("Lehrkräfte an weiterführenden Schulen (Sekundarstufe)", "Beschäftigte dieser Berufsgruppe unterrichten Schülerinnen und Schüler auf der Sekundarstufe in Sprachfächern, Mathematik, Naturwissenschaften, Gesellschaftskunde, Bildender Kunst, kaufmännischen Fächern und anderen Schulfächern.", ""),
    133: ("Grundschullehrkräfte", "Beschäftigte dieser Berufsgruppe unterrichten in Grundschulen oder erteilen Privatunterricht auf der Primarstufe; sie lehren Lesen, Schreiben, Rechnen und andere Grundschulfächer.", ""),
    134: ("Erzieherinnen und Erzieher (Vorschule)", "Beschäftigte dieser Berufsgruppe organisieren Gruppen- und Einzelaktivitäten in Kindergärten und Kindertagesstätten für Kinder im Vorschulalter.", ""),
    135: ("Sonderpädagogische Lehrkräfte", "Beschäftigte dieser Berufsgruppe unterrichten Personen mit Behinderungen; sie lehren blinde oder gehörlose Schülerinnen und Schüler sowie geistig oder körperlich beeinträchtigte Kinder.", ""),
    139: ("Lehrkräfte anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Lehr- und verwandte Tätigkeiten, die nicht anderweitig klassifiziert sind.", ""),
    141: ("Geistliche und Ordensmitglieder", "Beschäftigte dieser Berufsgruppe üben religiöse Dienste an Gemeindemitgliedern aus und verbreiten Glaubenslehren.", ""),
    149: ("Religionsdiener anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen religiöse Tätigkeiten, die nicht anderweitig klassifiziert sind.", ""),
    151: ("Autoren und Kritiker", "Beschäftigte dieser Berufsgruppe verfassen literarische Werke und schreiben Kritiken über literarische, künstlerische und musikalische Werke sowie Aufführungen.", ""),
    159: ("Autoren, Journalisten und verwandte Schreibberufe anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe verfassen schriftliches Material zur Information, Unterhaltung oder Beeinflussung der Öffentlichkeit, das nicht anderweitig klassifiziert ist.", ""),
    160: ("Bildhauer, Maler, Fotografen und verwandte bildende Künstler, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe können beliebige, aber nicht alle Tätigkeiten der Berufsgruppen 1-61 bis 1-63 ausüben.", ""),
    161: ("Bildhauer, Maler und verwandte bildende Künstler", "Beschäftigte dieser Berufsgruppe schaffen und gestalten künstlerische Werke durch Bildhauen, Malen, Zeichnen, Gravieren und Radieren; restaurieren beschädigte Gemälde.", ""),
    162: ("Gebrauchsgrafiker und Designer", "Beschäftigte dieser Berufsgruppe setzen künstlerische Mittel für illustrative, dekorative und werbliche Zwecke ein; entwerfen Inneneinrichtungen und Industriedesign.", ""),
    163: ("Fotografen und Kameraleute", "Beschäftigte dieser Berufsgruppe fotografieren und leiten die Arbeit an Film- und Fernsehkameras.", ""),
    170: ("Komponisten und darstellende Künstler, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe können beliebige, aber nicht alle Tätigkeiten der Berufsgruppen 1-71 bis 1-79 ausüben.", ""),
    171: ("Komponisten, Musiker und Sänger", "Beschäftigte dieser Berufsgruppe komponieren und arrangieren Musikwerke und führen diese auf.", ""),
    172: ("Choreografen und Tänzer", "Beschäftigte dieser Berufsgruppe choreografieren und führen Tänze auf.", ""),
    173: ("Schauspieler und Regisseure", "Beschäftigte dieser Berufsgruppe inszenieren und spielen in Theater-, Film- und Rundfunkproduktionen.", ""),
    174: ("Produzenten (Darstellende Künste)", "Beschäftigte dieser Berufsgruppe planen, organisieren und koordinieren Theaterproduktionen, Kinofilme sowie Radio- und Fernsehprogramme.", ""),
    175: ("Zirkusartisten", "Beschäftigte dieser Berufsgruppe führen verschiedene Darbietungen zur Unterhaltung von Zirkus- und anderen Zuschauern auf.", ""),
    179: ("Darstellende Künstler anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Unterhaltungsaufgaben, die nicht anderweitig klassifiziert sind.", ""),
    180: ("Sportler, Sportlerinnen und verwandte Berufe", "Beschäftigte dieser Berufsgruppe nehmen berufsmäßig an öffentlichen Sportwettkämpfen teil, trainieren Sportlerinnen und Sportler, leiten Wettkämpfe und unterrichten in körperlicher Fitness.", ""),
    191: ("Bibliothekare, Archivare und Museumskuratoren", "Beschäftigte dieser Berufsgruppe organisieren, entwickeln und betreuen Bibliotheken, Archive, Museen und Kunstgalerien.", ""),
    192: ("Soziologen, Anthropologen und verwandte Wissenschaftler", "Beschäftigte dieser Berufsgruppe forschen zu Ursprung, Entwicklung, Geschichte und Verhalten des Menschen als Individuum und als Mitglied der Gesellschaft.", ""),
    193: ("Sozialarbeiterinnen und Sozialarbeiter", "Beschäftigte dieser Berufsgruppe beaufsichtigen und erbringen soziale Dienstleistungen für Menschen in einer Gemeinschaft.", ""),
    194: ("Personal- und Berufsberater", "Beschäftigte dieser Berufsgruppe sind auf Personalarbeit, Berufsberatung und Berufsanalyse spezialisiert.", ""),
    195: ("Philologen, Übersetzer und Dolmetscher", "Beschäftigte dieser Berufsgruppe studieren Sprachen und übertragen schriftliche oder mündliche Inhalte von einer Sprache in eine andere.", ""),
    199: ("Andere Wissenschaftler, Techniker und verwandte Berufe", "Beschäftigte dieser Berufsgruppe erfüllen professionelle und technische Tätigkeiten, die nicht anderweitig klassifiziert sind, einschließlich Patentanwälte, Hauswirtschaftsberater, Werbefachleute und Versicherungszeichner.", ""),
    201: ("Gesetzgebende Beamte", "Beschäftigte dieser Berufsgruppe leiten oder nehmen an Verhandlungen gesetzgebender Körperschaften nationaler, staatlicher, provinzieller oder kommunaler Regierungen teil.", ""),
    202: ("Regierungsverwalter", "Beschäftigte dieser Berufsgruppe beraten Regierungen in Politikfragen und planen, organisieren und leiten Tätigkeiten von Regierungsabteilungen und -behörden.", ""),
    210: ("Unternehmensleiter, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe erfüllen Managementtätigkeiten wie in Untergruppe 2-1 beschrieben; Spezialisierung unbekannt.", ""),
    211: ("Generaldirektoren und Hauptgeschäftsführer", "Beschäftigte dieser Berufsgruppe planen, leiten, steuern und koordinieren auf Eigentümer- oder eigenem Auftrag die Tätigkeiten eines Industrie-, Handels-, Versorgungs-, Verkehrs-, Kommunikations- oder anderen Unternehmens.", ""),
    212: ("Produktionsleiter", "Beschäftigte dieser Berufsgruppe planen, organisieren, leiten und kontrollieren die Tätigkeiten der Produktionsabteilung eines Unternehmens.", ""),
    213: ("Verkaufsleiter", "Beschäftigte dieser Berufsgruppe planen, organisieren, koordinieren und leiten Groß- oder Einzelhandelsgeschäfte oder deren Niederlassungen auf Eigentümerauftrag.", ""),
    214: ("Direktoren (Gastronomie und Beherbergung)", "Beschäftigte dieser Berufsgruppe planen, organisieren, koordinieren und leiten Hotels, Restaurants, Gaststätten, Cafés, Bars, Campingplätze und ähnliche Betriebe auf Eigentümerauftrag.", ""),
    219: ("Unternehmensleiter anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Managementaufgaben, die nicht anderweitig klassifiziert sind.", ""),
    220: ("Aufsichtskräfte, Vorarbeiter und Inspektoren, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe erfüllen Aufsichtstätigkeiten wie in Untergruppe 2-2 beschrieben; Spezialisierung unbekannt.", ""),
    221: ("Büroleiter und Abteilungsleiter (Verwaltung)", "Beschäftigte dieser Berufsgruppe organisieren und überwachen die täglichen Tätigkeiten in Büros oder Büroabteilungen.", ""),
    222: ("Aufsichtskräfte im Transport- und Kommunikationswesen", "Beschäftigte dieser Berufsgruppe beaufsichtigen, steuern und kontrollieren Land-, Luft- und Wasserverkehrsbetriebe sowie Telekommunikationsbetriebe.", ""),
    223: ("Verkaufsaufsichtskräfte", "Beschäftigte dieser Berufsgruppe beaufsichtigen Verkaufspersonal in Groß- und Einzelhandelsbetrieben oder Verkaufsabteilungen.", ""),
    224: ("Hauswirtschaftliche Aufsichtskräfte", "Beschäftigte dieser Berufsgruppe organisieren, beaufsichtigen und führen hauswirtschaftliche Tätigkeiten in Hotels, Clubs und anderen Einrichtungen, an Bord von Schiffen und in Privathaushalten durch.", ""),
    225: ("Landwirtschaftliche Aufsichtskräfte", "Beschäftigte dieser Berufsgruppe beaufsichtigen die Tätigkeiten landwirtschaftlicher Arbeitskräfte.", ""),
    226: ("Produktionsaufseher und Generalvorarbeiter", "Beschäftigte dieser Berufsgruppe beaufsichtigen und koordinieren unter allgemeiner Leitung des Produktionsleiters die Produktionstätigkeiten eines abgegrenzten Bereichs oder einer Einheit.", ""),
    300: ("Bürokräfte und verwandte Berufe, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe können beliebige, aber nicht alle beruflichen Tätigkeiten der Untergruppen 3-1 bis 3-9 ausüben.", ""),
    310: ("Regierungsbeamte (ausführende Ebene)", "Beschäftigte dieser Berufsgruppe setzen staatliche Politikentscheidungen um und vollziehen Gesetze, Regeln und Vorschriften unter Leitung von Regierungsverwaltern.", ""),
    321: ("Stenografen, Schreibkräfte und Fernschreib­bediener", "Beschäftigte dieser Berufsgruppe nehmen Material stenografisch und per Schreibmaschine oder Fernschreiber auf.", ""),
    322: ("Lochkarten- und Lochstreifenbediener", "Beschäftigte dieser Berufsgruppe bedienen Maschinen, die Daten in Form von Lochungen in Karten oder Sonderbändern für die Datenverarbeitung aufzeichnen.", ""),
    331: ("Buchhalter und Kassierer", "Beschäftigte dieser Berufsgruppe führen Aufzeichnungen über finanzielle Transaktionen und verwalten Bargeld für ein Unternehmen oder dessen Kunden.", ""),
    339: ("Buchhalter, Kassierer und verwandte Berufe anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen buchhalterische, Kassen- und finanzbuchhalterische Aufgaben, die nicht anderweitig klassifiziert sind.", ""),
    341: ("Buchhalter- und Rechenmaschinenbediener", "Beschäftigte dieser Berufsgruppe bedienen Buchführungsmaschinen zur Aufzeichnung von Geschäftsvorgängen und Tastaturrechner für arithmetische Berechnungen.", ""),
    342: ("Bediener automatischer Datenverarbeitungsmaschinen", "Beschäftigte dieser Berufsgruppe bedienen automatische Maschinen, die wissenschaftliche, technische, kaufmännische oder andere Daten klassifizieren, sortieren, berechnen, zusammenfassen und aufzeichnen.", ""),
    360: ("Fahrzeugschaffner", "Beschäftigte dieser Berufsgruppe betreuen Personenzüge, Busse und andere öffentliche Verkehrsmittel, betreuen Fahrgäste und überwachen die Einhaltung von Sicherheitsvorschriften und Fahrplänen.", ""),
    370: ("Postzusteller und Briefverteiler", "Beschäftigte dieser Berufsgruppe sortieren und zustellen Briefsendungen und erfüllen andere Aufgaben im Zusammenhang mit der Postverteilung und Nachrichtenübermittlung.", ""),
    380: ("Telefon- und Telegrafenbediener", "Beschäftigte dieser Berufsgruppe übermitteln und empfangen Nachrichten durch Bedienung von Kabel- und Funktelefongeräten sowie Telegrafengeräten.", ""),
    391: ("Lagerbuchhalter und Lageristen", "Beschäftigte dieser Berufsgruppe führen Aufzeichnungen über empfangene, gewogene, ausgegebene, versandte oder eingelagerte Waren und Materialien.", ""),
    392: ("Material- und Produktionsplanungssachbearbeiter", "Beschäftigte dieser Berufsgruppe berechnen den Materialbedarf für Produktionsprogramme oder erstellen Produktionsablaufpläne.", ""),
    393: ("Korrespondenz- und Berichtssachbearbeiter", "Beschäftigte dieser Berufsgruppe verfassen Geschäftskorrespondenz, führen Personalakten und erledigen Spezialaufgaben in Versicherungs- oder Rechtsfragen.", ""),
    394: ("Empfangspersonal und Reisebüromitarbeiter", "Beschäftigte dieser Berufsgruppe vereinbaren Termine, empfangen Kunden, geben Auskunft, machen Reisearrangements und nehmen Zimmerreservierungen vor.", ""),
    395: ("Bibliotheksassistenten und Aktenkräfte", "Beschäftigte dieser Berufsgruppe pflegen Bibliotheksnachweise und führen Ablage- und Registrierarbeiten durch.", ""),
    399: ("Bürokräfte anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene kaufmännische Aufgaben, die anderweitig nicht klassifiziert sind.", ""),
    410: ("Selbstständige Kaufleute (Groß- und Einzelhandel)", "Beschäftigte dieser Berufsgruppe betreiben Groß- und Einzelhandelsgeschäfte auf eigene Rechnung oder in Gemeinschaft.", ""),
    422: ("Einkäufer", "Beschäftigte dieser Berufsgruppe kaufen Waren für den Wiederverkauf oder den Eigenbedarf im Auftrag von Groß-, Einzel-, Industrie- oder anderen Unternehmen.", ""),
    431: ("Technische Handelsvertreter und Kundendienstberater", "Beschäftigte dieser Berufsgruppe verkaufen technische Produkte oder Dienstleistungen unter Einsatz spezialisierter Kenntnisse und beraten Kunden zu technischen Geräten.", ""),
    432: ("Reisevertreter und Herstelleragenten", "Beschäftigte dieser Berufsgruppe verkaufen Waren auf Großhandelsbasis in einem zugewiesenen geografischen Gebiet.", ""),
    440: ("Versicherungs-, Immobilien-, Wertpapier- und Unternehmensdienstleistungsverkäufer sowie Auktionatoren, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe können beliebige, aber nicht alle Tätigkeiten der Untergruppe 4-4 ausüben.", ""),
    441: ("Versicherungs-, Immobilien- und Wertpapierverkäufer", "Beschäftigte dieser Berufsgruppe verkaufen Versicherungen, Immobilien und Wertpapiere.", ""),
    442: ("Unternehmensdienstleistungsverkäufer", "Beschäftigte dieser Berufsgruppe verkaufen Unternehmens- und Werbedienstleistungen.", ""),
    443: ("Auktionatoren und Schätzer", "Beschäftigte dieser Berufsgruppe versteigern Eigentum und Waren, bewerten Güter und Liegenschaften und schätzen Versicherungsschäden.", ""),
    451: ("Verkäufer, Ladengehilfen und Vorführer", "Beschäftigte dieser Berufsgruppe verkaufen und demonstrieren Waren in Groß- und Einzelhandelsbetrieben.", ""),
    452: ("Straßenverkäufer, Haustürverkäufer und Zeitungsverkäufer", "Beschäftigte dieser Berufsgruppe verkaufen Waren auf der Straße oder von Tür zu Tür.", ""),
    490: ("Kaufleute und Verkäufer anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Verkaufsaufgaben, die anderweitig nicht klassifiziert sind, einschließlich Pfandleiher und Süßwarenverkäufer in Unterhaltungsstätten.", ""),
    510: ("Selbstständige (Gastronomie, Beherbergung und Freizeitdienstleistungen)", "Beschäftigte dieser Berufsgruppe betreiben Gastronomie-, Beherbergungs- und Freizeitdienstleistungsbetriebe auf eigene Rechnung oder in Gemeinschaft.", ""),
    531: ("Köche", "Beschäftigte dieser Berufsgruppe bereiten Speisen zu und kochen in Hotels, Restaurants, öffentlichen Speisestätten, an Bord von Schiffen, in Eisenbahnzügen und in Privathaushalten.", ""),
    532: ("Kellner, Barkeeper und verwandte Berufe", "Beschäftigte dieser Berufsgruppe servieren Speisen und Getränke in Gaststätten, Clubs, Kantinen und anderen Einrichtungen sowie an Bord von Schiffen und in Eisenbahnzügen.", ""),
    540: ("Hauswirtschaftliche Dienstleistungskräfte anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erbringen persönliche und hauswirtschaftliche Dienstleistungen in Privathäusern, Hotels, an Bord von Schiffen und in öffentlichen Verkehrsmitteln.", ""),
    551: ("Hauswarte und Hausmeister", "Beschäftigte dieser Berufsgruppe betreuen Miethäuser, Bürogebäude, Kirchen und andere Gebäude und halten diese in Ordnung und Sauberkeit.", ""),
    552: ("Reinigungskräfte und verwandte Berufe", "Beschäftigte dieser Berufsgruppe reinigen Innenräume, Fenster und Schornsteine von Gebäuden.", ""),
    560: ("Wäscher, chemische Reiniger und Bügler", "Beschäftigte dieser Berufsgruppe waschen, chemisch reinigen und bügeln Bekleidung, Textilstoffe und ähnliche Artikel.", ""),
    570: ("Friseure, Barbiere, Kosmetiker und verwandte Berufe", "Beschäftigte dieser Berufsgruppe schneiden und frisieren Haare, tragen Kosmetik und Make-up auf und geben andere Arten von Behandlungen zur Verbesserung des äußeren Erscheinungsbildes.", ""),
    581: ("Feuerwehrleute", "Beschäftigte dieser Berufsgruppe bekämpfen Brände, beseitigen Brandgefahren und schützen Eigentum bei Bränden.", ""),
    582: ("Polizeibeamte und Kriminalbeamte", "Beschäftigte dieser Berufsgruppe erhalten Recht und Ordnung aufrecht, beugen Straftaten vor und verfolgen sie und setzen Gesetze und Vorschriften durch.", ""),
    583: ("Militärangehörige", "Beschäftigte dieser Berufsgruppe leisten als eingezogene, verpflichtete oder beauftragte Angehörige der Streitkräfte Dienst.", ""),
    589: ("Ordnungs- und Sicherheitsberufe anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Aufgaben zur Aufrechterhaltung von Recht und Ordnung und zum Schutz von Eigentum, die nicht anderweitig klassifiziert sind.", ""),
    591: ("Reiseführer", "Beschäftigte dieser Berufsgruppe begleiten Einzelpersonen und Gruppen auf Reisen, Besichtigungstouren und Ausflügen.", ""),
    592: ("Bestatter und Einbalsamierer", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Aufgaben im Zusammenhang mit der Bestattung von Verstorbenen.", ""),
    599: ("Sonstige Dienstleistungsberufe", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Dienstleistungsaufgaben, die nicht anderweitig klassifiziert sind.", ""),
    611: ("Landwirte (Gemischtbetrieb)", "Beschäftigte dieser Berufsgruppe bewirtschaften Gemischtbetriebe auf eigene Rechnung oder in Gemeinschaft und erzeugen verschiedene landwirtschaftliche Produkte und tierische Erzeugnisse.", ""),
    612: ("Landwirte (Spezialbetrieb)", "Beschäftigte dieser Berufsgruppe bewirtschaften Spezialbetriebe auf eigene Rechnung oder in Gemeinschaft.", ""),
    621: ("Landwirtschaftliche Hilfsarbeiter (allgemein)", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Aufgaben in der Viehzucht, im Feldfruchtanbau und bei der Wartung landwirtschaftlicher Gebäude und Geräte.", ""),
    622: ("Feldfrucht- und Gemüsearbeiter", "Beschäftigte dieser Berufsgruppe führen Tätigkeiten beim Pflanzen, Kultivieren und Ernten von Feldfrüchten aus.", ""),
    623: ("Obstgarten-, Weinberg- und Baumkulturarbeiter", "Beschäftigte dieser Berufsgruppe führen Tätigkeiten bei der Kultivierung von Bäumen für deren Früchte, Blätter oder Saft aus.", ""),
    624: ("Viehhaltungsarbeiter", "Beschäftigte dieser Berufsgruppe erfüllen Aufgaben in der Zucht und Haltung von Vieh.", ""),
    625: ("Milchwirtschaftliche Arbeitskräfte", "Beschäftigte dieser Berufsgruppe erfüllen Aufgaben in der Milchwirtschaft einschließlich Zucht, Haltung und Melken von Milchtieren.", ""),
    626: ("Geflügelwirtschaftliche Arbeitskräfte", "Beschäftigte dieser Berufsgruppe erfüllen Aufgaben in der Geflügelwirtschaft.", ""),
    627: ("Gärtnereikräfte und Gärtner", "Beschäftigte dieser Berufsgruppe erfüllen Tätigkeiten in der Gärtnerei, im Gartenbau und in der Freilandgemüsezucht sowie bei der Anzucht und Pflege von Bäumen, Sträuchern, Blumen und anderen Pflanzen.", ""),
    628: ("Landwirtschaftliche Maschinenbediener", "Beschäftigte dieser Berufsgruppe bedienen und warten landwirtschaftliche Maschinen und Geräte.", ""),
    629: ("Land- und tierzüchterische Arbeitskräfte anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Aufgaben in Landwirtschaft und Tierhaltung, die nicht anderweitig klassifiziert sind.", ""),
    631: ("Holzfäller und Rücker", "Beschäftigte dieser Berufsgruppe fällen Bäume, sägen sie zu Stämmen und führen andere Holzeinschlagtätigkeiten durch.", ""),
    632: ("Forstarbeiter (außer Holzeinschlag)", "Beschäftigte dieser Berufsgruppe beaufsichtigen und führen Tätigkeiten in Kultivierung, Erhaltung und Bewirtschaftung von Wäldern durch.", ""),
    641: ("Fischer", "Beschäftigte dieser Berufsgruppe fangen Fische als Besatzungsmitglieder von Fischereifahrzeugen oder in Binnen- und Küstengewässern.", ""),
    649: ("Fischer, Jäger und verwandte Berufe anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Fischerei-, Jagd- und verwandte Tätigkeiten, die nicht anderweitig klassifiziert sind.", ""),
    711: ("Bergleute und Steinbrucharbeiter", "Beschäftigte dieser Berufsgruppe fördern feste Mineralien aus unterirdischen oder oberirdischen Bergwerken und Steinbrüchen.", ""),
    712: ("Aufbereiter von Mineralien und Gestein", "Beschäftigte dieser Berufsgruppe bereiten Erze, Gestein und andere Mineralien für Verteilung oder Weiterverarbeitung auf.", ""),
    713: ("Bohrarbeiter und verwandte Berufe", "Beschäftigte dieser Berufsgruppe errichten und betreiben Bohreinrichtungen und führen verwandte Tätigkeiten beim Abteufen und Betrieb von Bohrlöchern aus.", ""),
    720: ("Metallbearbeiter, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe führen Metallverarbeitungsarbeiten wie in Untergruppe 7-2 beschrieben aus; Spezialisierung unbekannt.", ""),
    721: ("Schmelz-, Konvertier- und Raffinationsofenbediener", "Beschäftigte dieser Berufsgruppe bedienen Hochöfen, Konverter und Raffinations­öfen zur Gewinnung und Verarbeitung von Eisen- und Nichteisenmetallen.", ""),
    722: ("Walzwerkarbeiter", "Beschäftigte dieser Berufsgruppe bedienen Walzwerke zur Verformung von Metall.", ""),
    723: ("Metallschmelzer und Wiedererwärmer", "Beschäftigte dieser Berufsgruppe bedienen Öfen zum Schmelzen oder Wiedererwärmen von Metallen.", ""),
    724: ("Metallgießer", "Beschäftigte dieser Berufsgruppe gießen Metall in Formen und bedienen Metallgussmaschinen.", ""),
    725: ("Former und Kernmacher", "Beschäftigte dieser Berufsgruppe stellen Sandformen und Kerne für den Metallguss her.", ""),
    726: ("Glüher, Härter und Einsatzhärter", "Beschäftigte dieser Berufsgruppe verändern die physikalischen Eigenschaften von Metallgegenständen durch Wärmebehandlung, Abkühlung und chemische Behandlung.", ""),
    727: ("Zieher und Strangpresser", "Beschäftigte dieser Berufsgruppe ziehen und strangpressen Metalle zu Draht, Rohren und ähnlichen Erzeugnissen.", ""),
    728: ("Galvaniseure und Metall­beschichter", "Beschäftigte dieser Berufsgruppe betreiben Anlagen zur Galvanisierung und Beschichtung von Metallerzeugnissen.", ""),
    729: ("Metallbearbeiter anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Metallverarbeitungsaufgaben, die nicht anderweitig klassifiziert sind.", ""),
    731: ("Holzkonservierer", "Beschäftigte dieser Berufsgruppe trocknen und konservieren Holz.", ""),
    732: ("Säger, Furnierhersteller und verwandte Holzverarbeitungskräfte", "Beschäftigte dieser Berufsgruppe bedienen Maschinen oder verwenden Handwerkzeug zum Sägen und zur Herstellung von Furnieren und Sperrholz.", ""),
    733: ("Zellstoffaufbereiter", "Beschäftigte dieser Berufsgruppe bereiten Zellstoff für die Papierherstellung auf.", ""),
    734: ("Papiermacher", "Beschäftigte dieser Berufsgruppe stellen Papier von Hand oder mit Maschinen her.", ""),
    741: ("Brecher, Mahler und Mischer", "Beschäftigte dieser Berufsgruppe zerkleinern, mahlen, mischen und vermengen Chemikalien und andere Materialien in chemischen Prozessen.", ""),
    742: ("Kocher, Röster und verwandte Wärmebehandlungskräfte", "Beschäftigte dieser Berufsgruppe führen Koch-, Röst- und andere Wärmebehandlungsarbeiten in chemischen Prozessen durch.", ""),
    743: ("Filter- und Trennmaschinenbediener", "Beschäftigte dieser Berufsgruppe bedienen Vorrichtungen zur Filtration und Trennung von Chemikalien und Materialien.", ""),
    744: ("Destillations- und Reaktorbediener", "Beschäftigte dieser Berufsgruppe destillieren und raffinieren Chemikalien (außer Erdöl).", ""),
    745: ("Erdölraffinationsarbeiter", "Beschäftigte dieser Berufsgruppe raffinieren, destillieren und verarbeiten Erdöl und Erdölerzeugnisse.", ""),
    749: ("Chemische Verfahrensarbeiter anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Aufgaben in der chemischen und verwandten Verarbeitung, die nicht anderweitig klassifiziert sind.", ""),
    750: ("Spinner, Weber, Wirker, Färber und verwandte Berufe, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe können beliebige, aber nicht alle Tätigkeiten der Untergruppe 7-5 ausüben.", ""),
    751: ("Faserzubereiter", "Beschäftigte dieser Berufsgruppe bereiten Wolle, Baumwolle, Flachs, Jute, Hanf und andere natürliche Textilfasern für das Spinnen und Wickeln vor.", ""),
    752: ("Spinner und Wickler", "Beschäftigte dieser Berufsgruppe spinnen, zwirnen und wickeln Fäden und Garne aus natürlichen Textilfasern.", ""),
    753: ("Web- und Wirkmaschineneinrichter und Jacquardkartenbereiter", "Beschäftigte dieser Berufsgruppe richten Web- und Wirkmaschinen ein und warten sie und bereiten Jacquardkarten vor.", ""),
    754: ("Weber und verwandte Berufe", "Beschäftigte dieser Berufsgruppe weben Stoffe auf Hand- oder Maschinenwebstühlen.", ""),
    755: ("Wirker und Stricker", "Beschäftigte dieser Berufsgruppe wirken Kleidungsstücke, Stoffe und andere Artikel von Hand oder mit Maschinen.", ""),
    756: ("Bleicher, Färber und Textilveredler", "Beschäftigte dieser Berufsgruppe bleichen, färben und veredeln anderweitig Fasern, Garne, Stoffe und andere Textilerzeugnisse.", ""),
    757: ("Seiler und Strickmacher", "Beschäftigte dieser Berufsgruppe stellen Seile aus natürlichen und künstlichen Fasern her.", ""),
    759: ("Spinner, Weber, Wirker, Färber und verwandte Berufe anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Tätigkeiten in der Textilstoffherstellung, die nicht anderweitig klassifiziert sind.", ""),
    761: ("Gerber und Fellbereiter", "Beschäftigte dieser Berufsgruppe stellen Leder aus Häuten und Fellen her.", ""),
    762: ("Pelzzurichter", "Beschäftigte dieser Berufsgruppe bereiten pelz- oder wolletragendende Felle für die Herstellung von Kleidungsstücken und anderen Erzeugnissen vor.", ""),
    771: ("Getreidemüller und verwandte Berufe", "Beschäftigte dieser Berufsgruppe zerkleinern, mahlen, mischen und verarbeiten anderweitig Getreide, Gewürze und verwandte Lebensmittel.", ""),
    772: ("Zuckerverarbeiter und -raffinierer", "Beschäftigte dieser Berufsgruppe bedienen Anlagen zur Verarbeitung von Zuckerrohr und Zuckerrüben und stellen Raffinadezucker her.", ""),
    773: ("Fleischer und Fleischverarbeiter", "Beschäftigte dieser Berufsgruppe schlachten Tiere, zerlegen und richten Fleisch her und stellen Würste und andere Fleischwaren her.", ""),
    774: ("Lebensmittelkonservierer", "Beschäftigte dieser Berufsgruppe kochen, räuchern, trocknen, gefrieren oder dehydrieren Lebensmittel für die Konservierung.", ""),
    775: ("Milchwirtschaftliche Verarbeiter", "Beschäftigte dieser Berufsgruppe verarbeiten Milch und Rahm und stellen Milcherzeugnisse her.", ""),
    776: ("Bäcker, Konditoren und Süßwarenhersteller", "Beschäftigte dieser Berufsgruppe stellen verschiedene Arten von Brot, Kuchen und anderen Mehlprodukten sowie Schokoladen- und Zuckerwaren her.", ""),
    777: ("Tee-, Kaffee- und Kakaoverarbeiter", "Beschäftigte dieser Berufsgruppe kosten und klassifizieren Kaffee- und Teesorten und bereiten Kaffeebohnen, Zichorie und Kakaobohnen auf.", ""),
    778: ("Brauer, Weinmacher und Getränkehersteller", "Beschäftigte dieser Berufsgruppe mischen, pressen, mälzen und vergären Getreide und Früchte zur Herstellung von Malzgetränken, Wein, Fruchtsäften und anderen alkoholischen und nichtalkoholischen Getränken.", ""),
    779: ("Lebensmittel- und Getränkehersteller anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Aufgaben in der Lebensmittelverarbeitung, die nicht anderweitig klassifiziert sind.", ""),
    781: ("Tabakaufbereiter", "Beschäftigte dieser Berufsgruppe bereiten Tabakblätter für die Herstellung von Tabakwaren auf.", ""),
    782: ("Zigarrenmacher", "Beschäftigte dieser Berufsgruppe stellen Zigarren von Hand oder mit Maschinen her.", ""),
    783: ("Zigarettenmacher", "Beschäftigte dieser Berufsgruppe stellen Zigaretten mit Maschinen oder von Hand her.", ""),
    789: ("Tabakaufbereiter und Tabakwarenhersteller anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Aufgaben in der Tabakverarbeitung, die nicht anderweitig klassifiziert sind.", ""),
    791: ("Schneider und Damenschneiderinnen", "Beschäftigte dieser Berufsgruppe fertigen vollständige maßgefertigte Bekleidungsstücke, führen schwierigere Näharbeiten in der Konfektion aus und ändern und reparieren Bekleidung.", ""),
    792: ("Kürschner und verwandte Berufe", "Beschäftigte dieser Berufsgruppe fertigen, ändern, reparieren und restaurieren Kleidungsstücke und andere Artikel aus Pelz.", ""),
    793: ("Putzmacherinnen und Hutmacher", "Beschäftigte dieser Berufsgruppe fertigen und veredeln Hüte.", ""),
    794: ("Schnittmustermacher und Zuschnitter", "Beschäftigte dieser Berufsgruppe erstellen Schnittmuster und markieren und schneiden Materialien bei der Herstellung von Bekleidung, Handschuhen und anderen Textilwaren.", ""),
    795: ("Näher und Sticker", "Beschäftigte dieser Berufsgruppe nähen und besticken Kleidungsstücke, Handschuhe und verschiedene Erzeugnisse aus Pelz, Textilien und ähnlichen Materialien.", ""),
    796: ("Polsterer und verwandte Berufe", "Beschäftigte dieser Berufsgruppe polstern Möbel, stellen Matratzen her und fertigen sowie installieren Textil- und Lederausstattungen.", ""),
    799: ("Schneider, Näher, Polsterer und verwandte Berufe anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Aufgaben in der Herstellung und Reparatur von Bekleidung, Polsterwaren und verwandten Erzeugnissen, die nicht anderweitig klassifiziert sind.", ""),
    801: ("Schuhmacher und Schuhreperateure", "Beschäftigte dieser Berufsgruppe fertigen und reparieren Schuhwerk hauptsächlich aus Leder.", ""),
    802: ("Schuhzuschnitter, Aufleister, Näher und verwandte Berufe", "Beschäftigte dieser Berufsgruppe fertigen Schuhteile und führen Spezialaufgaben in der Herstellung von Schuhen aus Leder und ähnlichen Materialien aus.", ""),
    803: ("Lederwarenhersteller", "Beschäftigte dieser Berufsgruppe fertigen und reparieren Artikel hauptsächlich aus Leder und ähnlichen Materialien (außer Schuhwerk, Bekleidung und Handschuhen).", ""),
    810: ("Holzbearbeiter, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe führen Holzbearbeitungsarbeiten aus; Spezialisierung unbekannt.", ""),
    811: ("Schreiner und Tischler (Möbel)", "Beschäftigte dieser Berufsgruppe führen die anspruchsvolleren Arbeiten in der Herstellung und Reparatur von Holzmöbeln, Holzeinbauten und ähnlichen Gegenständen aus.", ""),
    812: ("Holzbearbeiter", "Beschäftigte dieser Berufsgruppe schneiden und formen Holz mit Handwerkzeugen oder durch Einrichten und Bedienen von Holzbearbeitungsmaschinen.", ""),
    819: ("Schreiner, Tischler und verwandte Holzbearbeiter anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Holzbearbeitungsaufgaben, die nicht anderweitig klassifiziert sind.", ""),
    820: ("Steinmetze und Steinhauer", "Beschäftigte dieser Berufsgruppe hauen, formen und veredeln Granit, Kalkstein, Marmor, Sandstein und andere Gesteine für Bau-, Schmuck-, Denkmalpflege- und andere Zwecke.", ""),
    830: ("Schmiede, Werkzeugmacher und Werkzeugmaschinenbediener, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe hämmern und schmieden Metall und führen andere Metallbearbeitungsarbeiten aus; Spezialisierung unbekannt.", ""),
    831: ("Schmiede, Hammerschmiede und Gesenkschmied-Pressenbediener", "Beschäftigte dieser Berufsgruppe hämmern und schmieden Stäbe, Stangen, Barren und Platten aus Eisen, Stahl oder anderen Metallen zur Herstellung verschiedener Werkzeuge, Metallerzeugnisse und landwirtschaftlicher Geräte.", ""),
    832: ("Werkzeugmacher, Metallmustermacher und Anreißer", "Beschäftigte dieser Berufsgruppe fertigen Werkzeuge, Gesenke, Vorrichtungen und andere Metallartikel mit Hand- und Maschinenwerkezugen auf engen Toleranzen.", ""),
    833: ("Werkzeugmaschineneinrichter", "Beschäftigte dieser Berufsgruppe richten zerspanende Maschinen auf enge Toleranzen ein.", ""),
    834: ("Werkzeugmaschinenbediener", "Beschäftigte dieser Berufsgruppe bedienen automatische oder halbautomatische Metallbearbeitungsmaschinen, die von Maschineneinrichtern für Serienarbeiten eingerichtet wurden.", ""),
    835: ("Metallschleifer, Polierer und Werkzeugschärfer", "Beschäftigte dieser Berufsgruppe schleifen und polieren Metalloberflächen und schärfen Werkzeuge.", ""),
    839: ("Schmiede, Werkzeugmacher und Werkzeugmaschinenbediener anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen verschiedene Metallbearbeitungsaufgaben, die nicht anderweitig klassifiziert sind.", ""),
    841: ("Maschinenschlosser und Maschinenmonteure", "Beschäftigte dieser Berufsgruppe montieren, installieren, warten und reparieren Maschinen, Motoren und mechanische Ausrüstungen (außer Elektroanlagen).", ""),
    842: ("Uhrmacher, Fein- und Präzisionsmechaniker", "Beschäftigte dieser Berufsgruppe fertigen und reparieren Uhren, Chronometer, Präzisionsinstrumente, optische Geräte und medizinische Hilfsmittel.", ""),
    843: ("Kraftfahrzeugmechaniker", "Beschäftigte dieser Berufsgruppe warten und reparieren mechanische und verwandte Ausrüstungen von Personen- und Lieferwagen, Lastkraftwagen und anderen Kraftfahrzeugen.", ""),
    844: ("Flugzeugmotorenmechaniker", "Beschäftigte dieser Berufsgruppe warten, reparieren und überholen Flugzeugmotoren.", ""),
    849: ("Maschinenschlosser, Maschinenmonteure und Feinmechaniker anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Aufgaben in Maschinenmontage und Feinmechanik (außer elektrisch), die nicht anderweitig klassifiziert sind.", ""),
    851: ("Elektroinstallateure", "Beschäftigte dieser Berufsgruppe montieren, stellen ein und reparieren Elektromaschinen und andere elektrische Geräte in Fabrik, Werkstatt oder am Einsatzort.", ""),
    852: ("Elektronikfachkräfte", "Beschäftigte dieser Berufsgruppe montieren, stellen ein und reparieren elektronische Geräte in Fabrik, Werkstatt oder am Einsatzort.", ""),
    853: ("Elektro- und Elektronikmonteure", "Beschäftigte dieser Berufsgruppe montieren Fertigteile zur Herstellung von Elektro- und Elektronikgeräten.", ""),
    854: ("Radio- und Fernsehtechniker", "Beschäftigte dieser Berufsgruppe reparieren Radio- und Fernsehgeräte in der Werkstatt oder am Einsatzort.", ""),
    855: ("Elektroinstallateure (Leitungsbau)", "Beschäftigte dieser Berufsgruppe installieren, warten und reparieren Elektroinstallationen und zugehörige Ausrüstungen in Gebäuden, Luftfahrzeugen, Kraftfahrzeugen und Schiffen.", ""),
    856: ("Telefon- und Telegrafenmonteure", "Beschäftigte dieser Berufsgruppe installieren, warten und reparieren Telefon- und Telegrafengeräte in der Zentrale oder am Einsatzort.", ""),
    857: ("Freileitungsmonteure und Kabelspleißer", "Beschäftigte dieser Berufsgruppe errichten, installieren und reparieren elektrische Leitungen und verbinden Kabel.", ""),
    859: ("Elektroinstallateure und verwandte Elektro- und Elektronikfachkräfte anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe sind in der Elektro- und Elektronikinstallation und verwandten Arbeiten tätig, die nicht anderweitig klassifiziert sind.", ""),
    861: ("Rundfunksender-Techniker", "Beschäftigte dieser Berufsgruppe bedienen und steuern Geräte zur Übertragung von Radio- oder Fernsehsendungen.", ""),
    862: ("Tontechniker und Filmvorführer", "Beschäftigte dieser Berufsgruppe installieren und bedienen Tonaufnahme- und Verstärkeranlagen und führen Kinovorführungen durch.", ""),
    871: ("Klempner und Rohrinstallateure", "Beschäftigte dieser Berufsgruppe montieren, verlegen, installieren und reparieren Sanitärinstallationen, Rohre und Rohrleitungssysteme.", ""),
    872: ("Schweißer und Brennschneider", "Beschäftigte dieser Berufsgruppe verbinden und trennen Metallteile mit Flamme, elektrischem Lichtbogen und anderen Wärmequellen.", ""),
    873: ("Blechner und Blechschmiede", "Beschäftigte dieser Berufsgruppe fertigen, installieren und reparieren Artikel oder Teile von Artikeln aus Blech.", ""),
    874: ("Stahlbauer und Montageschlosser", "Beschäftigte dieser Berufsgruppe formen, montieren und errichten schwere Stahlträger und -platten zu Tragwerken oder Rahmen.", ""),
    880: ("Juweliere und Edelmetallfacharbeiter", "Beschäftigte dieser Berufsgruppe fertigen und reparieren Schmuck und Edelmetallwaren, schleifen und fassen Edelsteine und gravieren Schmuck- und Edelmetallerzeugnisse.", ""),
    891: ("Glasverarbeiter, Schneider, Schleifer und Veredler", "Beschäftigte dieser Berufsgruppe blasen, formen, pressen und walzen Formen aus Glasschmelze und schneiden, schleifen und polieren Glas.", ""),
    892: ("Töpfer und verwandte Ton- und Schleifstoffformer", "Beschäftigte dieser Berufsgruppe stellen Töpferwaren, Porzellan, Ziegelsteine, Fliesen und Schleifscheiben her.", ""),
    893: ("Ofenmeister für Glas und Keramik", "Beschäftigte dieser Berufsgruppe bedienen Schmelzöfen und Brennöfen in der Glas- und Keramikherstellung.", ""),
    894: ("Glasgraveure und Glasätzer", "Beschäftigte dieser Berufsgruppe gravieren und ätzen Muster auf Glasartikel.", ""),
    895: ("Glas- und Keramikmaler und -dekorateure", "Beschäftigte dieser Berufsgruppe dekorieren Glas- und Keramikartikel.", ""),
    899: ("Glasverarbeiter, Töpfer und verwandte Berufe anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Aufgaben in der Glasverarbeitung und Keramikherstellung, die nicht anderweitig klassifiziert sind.", ""),
    901: ("Gummi- und Kunststoffverarbeiter (außer Reifenhersteller und -vulkanisierer)", "Beschäftigte dieser Berufsgruppe verarbeiten Rohgummi und Gummimischungen und fertigen Erzeugnisse aus Natur- und Synthesekautschuk sowie Kunststoffen.", ""),
    902: ("Reifenhersteller und -vulkanisierer", "Beschäftigte dieser Berufsgruppe stellen Luftreifen für Fahrräder, Kraftfahrzeuge, Traktoren und Luftfahrzeuge her.", ""),
    910: ("Papier- und Kartonwarenerzeuger", "Beschäftigte dieser Berufsgruppe stellen Schachteln, Umschläge, Beutel und andere Erzeugnisse aus Papier, Pappe und ähnlichen Materialien her.", ""),
    920: ("Graphisches Gewerbe (allgemein)", "Beschäftigte dieser Berufsgruppe setzen Schrift, gießen und gravieren Druckplatten und bedienen Druckmaschinen; binden Bücher; entwickeln und vervielfältigen fotografische Aufnahmen und Filmkopien.", ""),
    921: ("Schriftsetzer und Typografen", "Beschäftigte dieser Berufsgruppe setzen und arrangieren Drucktypen von Hand und mit Maschinen.", ""),
    922: ("Drucker (Maschinenführer)", "Beschäftigte dieser Berufsgruppe richten verschiedene Arten von Druckmaschinen ein und bedienen sie.", ""),
    923: ("Stereotypeure und Galvanotypeure", "Beschäftigte dieser Berufsgruppe stellen Druckplatten aus gesetzten Typen im Stereo- und Galvanotypieverfahren her.", ""),
    924: ("Druckgraveure (außer Fotograveure)", "Beschäftigte dieser Berufsgruppe gravieren Lithografiesteine und Druckplatten, Walzen, Matrizen und Blöcke auf verschiedene Weisen außer dem Fotogravurverfahren.", ""),
    925: ("Fotograveure", "Beschäftigte dieser Berufsgruppe bereiten Metallplatten im Fotogravurverfahren für den Druck vor.", ""),
    926: ("Buchbinder und verwandte Berufe", "Beschäftigte dieser Berufsgruppe binden Buchdeckel an Bücher und führen buchbinderische Veredelungsarbeiten durch.", ""),
    927: ("Fotografische Dunkelkammerarbeiter", "Beschäftigte dieser Berufsgruppe entwickeln belichtetes fotografisches Film- und Bildmaterial und stellen Fotoabzüge her.", ""),
    929: ("Drucker und verwandte Berufe anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe führen Druckarbeiten aus, die nicht anderweitig klassifiziert sind.", ""),
    931: ("Baumaler und Anstreicher", "Beschäftigte dieser Berufsgruppe bereiten Oberflächen von Gebäuden und anderen Bauwerken für den Anstrich vor und tragen Schutz- und Dekorationsbeschichtungen auf.", ""),
    939: ("Maler anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe tragen dekorative und schützende Beschichtungen auf Artikel aus Holz, Metall, Textilien und anderen Materialien auf (außer Glas und Keramik).", ""),
    941: ("Musikinstrumentenmacher und -stimmer", "Beschäftigte dieser Berufsgruppe fertigen, reparieren und stimmen Saiten-, Blas- und Schlaginstrumente mit Hand- und Maschinenwerkzeugen.", ""),
    942: ("Korbflechter und Bürstenbinder", "Beschäftigte dieser Berufsgruppe flechten Körbe, fertigen Korbmöbel und stellen Bürsten und Besen zusammen.", ""),
    943: ("Nichtmetallische Mineralproduktehersteller", "Beschäftigte dieser Berufsgruppe stellen Erzeugnisse vor allem aus nichtmetallischen mineralischen Materialien her.", ""),
    949: ("Andere Produktions- und verwandte Arbeitskräfte", "Beschäftigte dieser Berufsgruppe sind Produktions- und verwandte Arbeitskräfte, die in keiner anderen Berufsgruppe klassifiziert sind.", ""),
    950: ("Bauarbeiter, Spezialisierung unbekannt", "Beschäftigte dieser Berufsgruppe können verschiedene Bau- und Reparaturarbeiten an Gebäuden und anderen Strukturen ausführen; Spezialisierung unbekannt.", ""),
    951: ("Maurer, Steinmetze und Fliesenleger", "Beschäftigte dieser Berufsgruppe errichten und reparieren Fundamente, Mauern und vollständige Tragwerke aus Ziegeln, Steinen und ähnlichen Materialien und belegen Wände, Decken und Fußböden mit Fliesen und Mosaikpanelen.", ""),
    952: ("Betonbauer, Zementfinisher und Terrazzoarbeiter", "Beschäftigte dieser Berufsgruppe errichten Stahlbetontragwerke, schaffen Schalungen, bewehren Beton, verlegen Stahlbetonbeläge, verputzen und reparieren Betonoberflächen und führen Terrazzoarbeiten durch.", ""),
    953: ("Dachdecker", "Beschäftigte dieser Berufsgruppe decken Dachstuhlkonstruktionen mit verschiedenen Materialarten.", ""),
    954: ("Zimmerleute, Tischler und Parkettleger", "Beschäftigte dieser Berufsgruppe schneiden, formen, montieren, errichten und warten verschiedene Holztragwerke und -einbauten mit Hand- und Maschinenwerkzeugen.", ""),
    955: ("Gipser und Putzer", "Beschäftigte dieser Berufsgruppe bringen Putz auf Wände und Decken von Gebäuden auf.", ""),
    956: ("Isolierer", "Beschäftigte dieser Berufsgruppe tragen Dämmmaterialien auf Gebäude, Kessel, Rohre und Kälte- und Klimaanlagen auf.", ""),
    957: ("Glaser", "Beschäftigte dieser Berufsgruppe schneiden, passen und setzen Glas in Fenster, Türen, Schaufenster und andere Rahmen ein.", ""),
    959: ("Bauarbeiter anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe führen verschiedene Bau- und Gebäudeinstandhaltungsarbeiten aus, die nicht anderweitig klassifiziert sind.", ""),
    961: ("Bediener stromerzeugender Maschinen", "Beschäftigte dieser Berufsgruppe bedienen Anlagen zur Stromerzeugung und steuern deren Verteilung.", ""),
    969: ("Bediener stationärer Maschinen und Anlagen anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe bedienen und warten stationäre Maschinen und zugehörige Anlagen, die nicht anderweitig klassifiziert sind.", ""),
    971: ("Hafenarbeiter und Lageristen", "Beschäftigte dieser Berufsgruppe verladen und entladen Fracht, transportieren Waren in Lagerhäusern und Märkten, verpacken und etikettieren Waren und bedienen Ballenpressen.", ""),
    972: ("Rigger und Kabelspleißer", "Beschäftigte dieser Berufsgruppe errichten Hebezeug für Hebe- und Zugarbeiten und installieren und warten Kabel, Seile und Drähte auf Baustellen, Schiffen, Luftfahrzeugen und anderen Orten.", ""),
    973: ("Kran- und Hebezeugführer", "Beschäftigte dieser Berufsgruppe bedienen Kräne und andere Hebe- und Zuggeräte.", ""),
    974: ("Erdbewegungsmaschinenführer", "Beschäftigte dieser Berufsgruppe baggern, planieren und verdichten Erdreich und ähnliche Materialien, mischen Beton und verlegen Fahrbahnbeläge aus Asphalt und Beton.", ""),
    979: ("Bediener von Materialumschlagsgeräten anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe erfüllen Aufgaben im Betrieb von Materialumschlagsgeräten, die nicht anderweitig klassifiziert sind.", ""),
    981: ("Decksmannschaft, Besatzungen von Binnenschiffen und Bootsleute", "Beschäftigte dieser Berufsgruppe führen Decksdienst an Bord unter Leitung der Decksoffiziere und ähnliche Aufgaben auf anderen Wasserfahrzeugen aus.", ""),
    982: ("Maschinenraumpersonal auf Schiffen", "Beschäftigte dieser Berufsgruppe bedienen und warten Schiffsmotoren, Kessel und mechanische Ausrüstungen an Bord unter Aufsicht der Maschinenoffiziere.", ""),
    983: ("Lokführer und Heizer", "Beschäftigte dieser Berufsgruppe fahren oder unterstützen das Fahren von Lokomotiven zum Transport von Personen und Fracht.", ""),
    984: ("Zugführer, Weichensteller und Rangierer", "Beschäftigte dieser Berufsgruppe begleiten Güterzüge, steuern den Eisenbahnverkehr durch Bedienung von Signalen, rangieren Rollmaterial und stellen Züge in Bahnhöfen zusammen.", ""),
    985: ("Kraftfahrzeugführer", "Beschäftigte dieser Berufsgruppe fahren Straßenbahnwagen und Kraftfahrzeuge zum Transport von Personen und Fracht.", ""),
    986: ("Fuhrleute und Viehtreiber", "Beschäftigte dieser Berufsgruppe lenken tiergezogene Fuhrwerke und Tiere zum Transport von Personen und Fracht.", ""),
    989: ("Transportgeräteführer anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe bedienen oder warten verschiedene Transportmittel und verwandte Anlagen, die nicht anderweitig klassifiziert sind.", ""),
    999: ("Sonstige Arbeitskräfte anderweitig nicht klassifiziert", "Beschäftigte dieser Berufsgruppe verrichten einfache und routinemäßige manuelle Tätigkeiten, die hauptsächlich körperliche Anstrengung erfordern und wenig oder keine Vorerfahrung voraussetzen, und die nicht von anderweitig klassifizierten Arbeitskräften ausgeführt werden.", ""),
}

# ---------------------------------------------------------------------------
# CSV einlesen
# ---------------------------------------------------------------------------

def read_csv(filename):
    rows = []
    with open(os.path.join(BASE, filename), encoding='utf-8', newline='') as f:
        reader = csv.DictReader(f)
        for row in reader:
            rows.append(row)
    return rows

major_rows = read_csv('hisco_major_group.csv')
minor_rows = read_csv('hisco_minor_group.csv')
unit_rows  = read_csv('hisco_unit_group.csv')

# ---------------------------------------------------------------------------
# Ausgabe-CSV schreiben
# ---------------------------------------------------------------------------

FIELDNAMES = ['level', 'code', 'label_en', 'label_de', 'description_en', 'description_de', 'translation_note']
out_path = os.path.join(BASE, 'hisco_hierarchy_de.csv')

notes_missing_major = []
notes_missing_minor = []
notes_missing_unit  = []

with open(out_path, 'w', encoding='utf-8', newline='') as f:
    writer = csv.DictWriter(f, fieldnames=FIELDNAMES, quoting=csv.QUOTE_ALL)
    writer.writeheader()

    total = 0

    # --- major ---
    for r in major_rows:
        code = int(r['major_id'])
        de = MAJOR_DE.get(code)
        if de is None:
            notes_missing_major.append(code)
            label_de, desc_de, note = '', '', 'Übersetzung fehlt'
        else:
            label_de, desc_de, note = de
        writer.writerow({
            'level':          'major',
            'code':           r['major_id'],
            'label_en':       r['label'],
            'label_de':       label_de,
            'description_en': r['description'],
            'description_de': desc_de,
            'translation_note': note,
        })
        total += 1

    # --- minor ---
    for r in minor_rows:
        code = int(r['minor_id'])
        de = MINOR_DE.get(code)
        if de is None:
            notes_missing_minor.append(code)
            label_de, desc_de, note = '', '', 'Übersetzung fehlt'
        else:
            label_de, desc_de, note = de
        writer.writerow({
            'level':          'minor',
            'code':           r['minor_id'],
            'label_en':       r['label'],
            'label_de':       label_de,
            'description_en': r['description'],
            'description_de': desc_de,
            'translation_note': note,
        })
        total += 1

    # --- unit ---
    for r in unit_rows:
        code = int(r['unit_id'])
        de = UNIT_DE.get(code)
        if de is None:
            notes_missing_unit.append(code)
            label_de, desc_de, note = '', '', 'Übersetzung fehlt'
        else:
            label_de, desc_de, note = de
        writer.writerow({
            'level':          'unit',
            'code':           r['unit_id'],
            'label_en':       r['label'],
            'label_de':       label_de,
            'description_en': r['description'],
            'description_de': desc_de,
            'translation_note': note,
        })
        total += 1

print(f'Zeilen geschrieben: {total}')
print(f'Erwartete Summe:    {len(major_rows) + len(minor_rows) + len(unit_rows)}')
if notes_missing_major: print('Fehlende major-Übersetzungen:', notes_missing_major)
if notes_missing_minor: print('Fehlende minor-Übersetzungen:', notes_missing_minor)
if notes_missing_unit:  print('Fehlende unit-Übersetzungen:', notes_missing_unit)

# ---------------------------------------------------------------------------
# Validierung: keine doppelten codes innerhalb derselben Ebene
# ---------------------------------------------------------------------------
seen = {'major': set(), 'minor': set(), 'unit': set()}
col_errors = 0
with open(out_path, encoding='utf-8', newline='') as f:
    reader = csv.DictReader(f)
    for row in reader:
        if len(row) != 7:
            col_errors += 1
        lvl = row['level']
        c   = row['code']
        if c in seen[lvl]:
            print(f'DUPLIKAT: level={lvl} code={c}')
        seen[lvl].add(c)

print(f'Spaltenfehler: {col_errors}')
print(f'Einzigartige major: {len(seen["major"])}, minor: {len(seen["minor"])}, unit: {len(seen["unit"])}')

# ---------------------------------------------------------------------------
# Notes-Datei schreiben
# ---------------------------------------------------------------------------

today = datetime.date.today().strftime('%Y-%m-%d')
notes_path = os.path.join(BASE, 'hisco_hierarchy_de_notes.md')
with open(notes_path, 'w', encoding='utf-8') as f:
    f.write(f"""# HISCO Hierarchie – Deutsche Übersetzungsnotizen

## Herkunft

Die Datei `hisco_hierarchy_de.csv` wurde aus den englischen HISCO-Katalogdateien abgeleitet:

- `hisco_major_group.csv`
- `hisco_minor_group.csv`
- `hisco_unit_group.csv`

Die englischen Originaldateien wurden **nicht verändert**.

## Bearbeitungsdatum

{today}

## Zeilenanzahl

| Ebene | Anzahl |
|-------|--------|
| major | {len(major_rows)} |
| minor | {len(minor_rows)} |
| unit  | {len(unit_rows)} |
| **Gesamt** | **{len(major_rows) + len(minor_rows) + len(unit_rows)}** |

## Übersetzungsentscheidungen

### Terminologische Grundsätze

- „not elsewhere classified" → „anderweitig nicht klassifiziert"
- „related workers" → je nach Kontext „verwandte Berufe" oder „verwandte Arbeitskräfte"
- Berufsgruppen werden als substantivische Gruppenbezeichnungen formuliert
  (z. B. „Chemiker", „Bürokräfte", „Dienstleistungsberufe")
- Historische und berufssoziologische Terminologie hat Vorrang vor heutigem Alltagsdeutsch

### Spezifische Hinweise

- **Hauptgruppen 0 und 1**: Im englischen Original identisch beschrieben
  (beide = „Professional, Technical and related workers"). Im Deutschen ebenfalls gleich
  übersetzt; translation_note verweist auf die gemeinsame HISCO-Hauptgruppe 0/1.
- **Hauptgruppen 7, 8 und 9**: Analog dazu = HISCO-Hauptgruppe 7/8/9.
- **minor_id 61 (Farmers)**: Der englische Begriff „Farmers" bezeichnet selbstständige
  Hofbewirtschafter. Die Übersetzung „Landwirte (Selbstständige)" folgt dem funktionalen
  Merkmal und unterscheidet sich bewusst vom historischen deutschen Standesbegriff „Bauer".
  Benutzeroberflächen des Moduls können beide Bezeichnungen separat referenzieren.
- **unit_id 124 (Solicitors)**: Britisch-rechtlicher Berufstitel ohne direkte deutsche
  Entsprechung; als „Rechtsbeistände" übersetzt; translation_note markiert die Unsicherheit.
- **unit_id 583 (Military)**: Breite Kategorie; als „Militärangehörige" übersetzt.

### Unsichere oder markierte Übersetzungen

Zeilen mit nicht-leerem `translation_note`-Feld enthalten:
- Hinweise auf Mehrfachbelegung derselben Beschreibung (Hauptgruppen 0/1 und 7/8/9)
- Fachterminologische Unsicherheiten (z. B. „Solicitors")
- Funktionale Unterschiede zwischen englischen und deutschen Berufsbegriffen
  (z. B. „Farmers" vs. „Bauern/Landwirte")

## Originaldaten unverändert

Die Spalten `label_en` und `description_en` in `hisco_hierarchy_de.csv` enthalten
die Inhalte der englischen Quelldateien ohne Änderungen.
""")

print('Notizen-Datei geschrieben.')
