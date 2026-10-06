import csv, re, sys
from collections import Counter
# Algeria gate. M7 revisit: fix-only pass, 19 link retargets (3908/3908 kept).
# Tree verified exact: ISO 3166-2:DZ 58/58 codes (6 display deviations noted
# below) + Law 26-06 (JO n25 2026) 59-69 numbering (mother-wilaya order, JO
# column per riadh2002 + enwiki Provinces-of-Algeria + yasserstudio/geoalgeria;
# el-amin-dev alphabetical variant rejected) + geoalgeria daira sets 548/548
# modulo BOD/Debdeb/Ain-Smara-as-communes (frwiki-confirmed). Code set ==
# baridimap-derived poste offices 3908/3908, zero rot; GN 2918/3162 agree,
# 119 renumbered-olds + 125 unverifiable GN-only deliberately absent.
# Links: geoalgeria ONS-join agrees 3878/3908; 30 conflicts adjudicated via
# frwiki/JO-91-306/OSM/GN -> 19 retargets (3 signals each), 8 keeps
# (BOD/Debdeb/Ain Smara communes + Dhayet spelling), 3 fuzzy-threshold
# variants agree. Coverage 547 -> 548/548 (Oued Morra). Zero multis: every
# office holds a unique code (1:1 code<->office per upstream).
# Run from repo root: python3 /tmp/geo-verify/M7/DZ/gate_dz.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/algeria-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/algeria-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/algeria-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-617', len(rows) == 617, str(len(rows)))
check('wilayas-69', sum(1 for r in rows if r['type'] == 'wilaya') == 69)
check('dairas-548', sum(1 for r in rows if r['type'] == 'daira') == 548)
# ISO 3166-2:DZ 58/58 (display deviations deliberate: 04 Oum El Bouaghi
# caps, 10 Bouira+diaeresis, 11 Tamanghasset for Tamanrasset, 42 Tipasa for
# Tipaza, 57 El M'ghair for El Meghaier, 58 El Menia for El Meniaa).
iso = {
 '01': 'Adrar',
 '02': 'Chlef',
 '03': 'Laghouat',
 '04': 'Oum El Bouaghi',
 '05': 'Batna',
 '06': 'Béjaïa',
 '07': 'Biskra',
 '08': 'Béchar',
 '09': 'Blida',
 '10': 'Bouïra',
 '11': 'Tamanghasset',
 '12': 'Tébessa',
 '13': 'Tlemcen',
 '14': 'Tiaret',
 '15': 'Tizi Ouzou',
 '16': 'Alger',
 '17': 'Djelfa',
 '18': 'Jijel',
 '19': 'Sétif',
 '20': 'Saïda',
 '21': 'Skikda',
 '22': 'Sidi Bel Abbès',
 '23': 'Annaba',
 '24': 'Guelma',
 '25': 'Constantine',
 '26': 'Médéa',
 '27': 'Mostaganem',
 '28': "M'Sila",
 '29': 'Mascara',
 '30': 'Ouargla',
 '31': 'Oran',
 '32': 'El Bayadh',
 '33': 'Illizi',
 '34': 'Bordj Bou Arréridj',
 '35': 'Boumerdès',
 '36': 'El Tarf',
 '37': 'Tindouf',
 '38': 'Tissemsilt',
 '39': 'El Oued',
 '40': 'Khenchela',
 '41': 'Souk Ahras',
 '42': 'Tipasa',
 '43': 'Mila',
 '44': 'Aïn Defla',
 '45': 'Naama',
 '46': 'Aïn Témouchent',
 '47': 'Ghardaïa',
 '48': 'Relizane',
 '49': 'Timimoun',
 '50': 'Bordj Badji Mokhtar',
 '51': 'Ouled Djellal',
 '52': 'Béni Abbès',
 '53': 'In Salah',
 '54': 'In Guezzam',
 '55': 'Touggourt',
 '56': 'Djanet',
 '57': "El M'ghair",
 '58': 'El Menia',
}
for code, name in iso.items():
    got = [r for r in rows if r['type'] == 'wilaya' and r['code'] == code]
    check(f'gov-{code}', len(got) == 1 and got[0]['name'] == name
          and got[0]['level'] == '1' and not got[0]['parent_source_id'],
          str(got))
# Law 26-06 59-69 (JO n25 numbering; mother wilaya per JO/glp23).
jo = {
 '59': ('Aflou', '03'),
 '60': ('Barika', '05'),
 '61': ('El Kantara', '07'),
 '62': ('Bir El Ater', '12'),
 '63': ('El Aricha', '13'),
 '64': ('Ksar Chellala', '14'),
 '65': ('Aïn Ouessara', '17'),
 '66': ('Messaad', '17'),
 '67': ('Ksar El Boukhari', '26'),
 '68': ('Bou Saâda', '28'),
 '69': ('El Abiodh Sidi Cheikh', '32'),
}
for code, (name, mother) in jo.items():
    got = [r for r in rows if r['type'] == 'wilaya' and r['code'] == code]
    check(f'gov-{code}', len(got) == 1 and got[0]['name'] == name
          and got[0]['level'] == '1' and not got[0]['parent_source_id'],
          str(got))
# Full wilaya -> dairas table (geoalgeria 548/548 modulo BOD/Debdeb/AinSmara).
table = {
 # 01 Adrar (6)
 'dz:wilaya:adrar': ['Adrar', 'Aoulef', 'Fenoughil', 'Reggane', 'Tsabit', 'Zaouiet Kounta'],
 # 02 Chlef (13)
 'dz:wilaya:chlef': ['Abou El Hassen', 'Aïn Merane', 'Boukadir', 'Béni Haoua', 'Chlef', 'El Karimia', 'El Marsa', 'Oued Fodda', 'Ouled Ben Abdelkader', 'Ouled Fares', 'Taougrite', 'Ténès', 'Zeboudja'],
 # 03 Laghouat (5)
 'dz:wilaya:laghouat': ['Aïn Madhi', "Hassi R'Mel", 'Ksar El Hirane', 'Laghouat', 'Sidi Makhlouf'],
 # 04 Oum El Bouaghi (12)
 'dz:wilaya:oum-el-bouaghi': ['Aïn Babouche', 'Aïn Beïda', 'Aïn Fakroun', 'Aïn Kercha', "Aïn M'lila", 'Dhalaa', 'Fkirina', 'Ksar Sbahi', 'Meskiana', 'Oum El Bouaghi', 'Sigus', 'Souk Naamane'],
 # 05 Batna (18)
 'dz:wilaya:batna': ['Arris', 'Aïn Djasser', 'Aïn Touta', 'Batna', 'Bouzina', 'Chemora', 'Elmadher', 'Ichmoul', 'Menaa', 'Merouana', "N'Gaous", 'Ouled Si Slimane', 'Ras El Aïoun', 'Seriana', "T'Kout", 'Tazoult', 'Teniet El Abed', 'Timgad'],
 # 06 Béjaïa (19)
 'dz:wilaya:bejaia': ['Adekar', 'Akbou', 'Amizour', 'Aokas', 'Barbacha', 'Béjaïa', 'Béni Maouche', 'Chemini', 'Darguina', 'El Kseur', 'Ighil Ali', 'Kherrata', 'Ouzellaguen', 'Seddouk', 'Sidi-Aïch', 'Souk El Ténine', 'Tazmalt', 'Tichy', 'Timezrit'],
 # 07 Biskra (7)
 'dz:wilaya:biskra': ['Biskra', 'Foughala', "M'Chounèche", 'Ourlal', 'Sidi Okba', 'Tolga', 'Zeribet El Oued'],
 # 08 Béchar (6)
 'dz:wilaya:bechar': ['Abadla', 'Béchar', 'Béni Ounif', 'Kenadsa', 'Lahmar', 'Taghit'],
 # 09 Blida (10)
 'dz:wilaya:blida': ['Blida', 'Boufarik', 'Bougara', 'Bouinan', 'El Affroun', 'Larbaa', 'Meftah', 'Mouzaïa', 'Oued Alleug', 'Ouled Yaich'],
 # 10 Bouïra (12)
 'dz:wilaya:bouira': ['Aïn Bessem', 'Bechloul', 'Bir Ghbalou', 'Bordj Okhriss', 'Bouira', 'El Hachimia', 'Haizer', 'Kadiria', 'Lakhdaria', "M'Chedallah", 'Souk El Khemis', 'Sour El Ghozlane'],
 # 11 Tamanghasset (3)
 'dz:wilaya:tamanghasset': ['Abalessa', 'Tamanrasset', 'Tazrouk'],
 # 12 Tébessa (10)
 'dz:wilaya:tebessa': ['Bir Mokkadem', 'Cheria', 'El Aouinet', 'El Kouif', 'El Ma Labiod', 'El Ogla', 'Morsott', 'Ouenza', 'Oum Ali', 'Tébessa'],
 # 13 Tlemcen (19)
 'dz:wilaya:tlemcen': ['Aïn Tallout', 'Bab El Assa', 'Bensekrane', 'Béni Boussaïd', 'Béni Snous', 'Chetouane', 'Fellaoucène', 'Ghazaouet', 'Hennaya', 'Honaïne', 'Maghnia', 'Mansourah', "Marsa Ben M'Hidi", 'Nédroma', 'Ouled Mimoun', 'Remchi', 'Sabra', 'Sebdou', 'Tlemcen'],
 # 14 Tiaret (12)
 'dz:wilaya:tiaret': ['Aïn Deheb', 'Aïn Kermes', 'Dahmouni', 'Frenda', 'Mahdia', 'Mechraa Sfa', 'Medroussa', 'Meghila', 'Oued Lili', 'Rahouia', 'Sougueur', 'Tiaret'],
 # 15 Tizi Ouzou (21)
 'dz:wilaya:tizi-ouzou': ['Azazga', 'Azeffoun', 'Aïn El Hammam', 'Boghni', 'Bouzeguène', 'Béni Douala', 'Béni Yenni', 'Draâ Ben Khedda', 'Draâ El Mizan', 'Iferhounène', 'Larbaâ Nath Irathen', 'Makouda', 'Mekla', 'Mâatkas', 'Ouacif', 'Ouadhia', 'Ouaguenoun', 'Tigzirt', 'Tizi Gheniff', 'Tizi Ouzou', 'Tizi Rached'],
 # 16 Alger (13)
 'dz:wilaya:alger': ['Bab El Oued', 'Baraki', 'Bir Mourad Raïs', 'Birtouta', 'Bouzareah', 'Chéraga', 'Dar El Beïda', 'Draria', 'El Harrach', 'Hussein Dey', 'Rouïba', "Sidi M'Hamed", 'Zéralda'],
 # 17 Djelfa (6)
 'dz:wilaya:djelfa': ['Aïn El Ibel', 'Charef', 'Dar Chioukh', 'Djelfa', 'El Idrissia', 'Hassi Bahbah'],
 # 18 Jijel (11)
 'dz:wilaya:jijel': ['Chekfa', 'Djimla', 'El Ancer', 'El Aouana', 'El Milia', 'Jijel', 'Settara', 'Sidi Maarouf', 'Taher', 'Texenna', 'Ziama Mansouriah'],
 # 19 Sétif (20)
 'dz:wilaya:setif': ['Amoucha', 'Aïn Arnat', 'Aïn Azel', 'Aïn El Kebira', 'Aïn Oulmene', 'Babor', 'Bir El Arch', 'Bouandas', 'Bougaa', 'Béni Aziz', 'Béni Ourtilane', 'Djemila', 'El Eulma', 'Guenzet', 'Guidjel', 'Hammam Guergour', 'Hammam Soukhna', 'Maoklane', 'Salah Bey', 'Sétif'],
 # 20 Saïda (6)
 'dz:wilaya:saida': ['Aïn El Hadjar', 'El Hassasna', 'Ouled Brahim', 'Saïda', 'Sidi Boubekeur', 'Youb'],
 # 21 Skikda (13)
 'dz:wilaya:skikda': ['Azzaba', 'Aïn Kechra', 'Ben Azzouz', 'Collo', 'El Hadaiek', 'El Harrouch', 'Ouled Attia', 'Oum Toub', 'Ramdane Djamel', 'Sidi Mezghiche', 'Skikda', 'Tamalous', 'Zitouna'],
 # 22 Sidi Bel Abbès (15)
 'dz:wilaya:sidi-bel-abbes': ['Aïn El Berd', 'Ben Badis', 'Marhoum', 'Merine', 'Mostefa Ben Brahim', 'Moulay Slissen', 'Ras El Ma', 'Sfisef', 'Sidi Ali Benyoub', 'Sidi Ali Boussidi', 'Sidi Bel Abbès', 'Sidi Lahcene', 'Telagh', 'Tenira', 'Tessala'],
 # 23 Annaba (6)
 'dz:wilaya:annaba': ['Annaba', 'Aïn Berda', 'Berrahal', 'Chetaïbi', 'El Bouni', 'El Hadjar'],
 # 24 Guelma (10)
 'dz:wilaya:guelma': ['Aïn Makhlouf', 'Bouchegouf', 'Guelaât Bou Sbaâ', 'Guelma', 'Hammam Debagh', "Hammam N'Bails", 'Houari Boumédiène', 'Héliopolis', 'Khezarra', 'Oued Zenati'],
 # 25 Constantine (6)
 'dz:wilaya:constantine': ['Aïn Abid', 'Constantine', 'El Khroub', 'Hamma Bouziane', 'Ibn Ziad', 'Zighoud Youcef'],
 # 26 Médéa (13)
 'dz:wilaya:medea': ['Berrouaghia', 'Béni Slimane', 'El Azizia', 'El Guelb El Kebir', 'El Omaria', 'Médéa', 'Ouamri', 'Ouzera', 'Seghouane', 'Si Mahdjoub', 'Sidi Naamane', 'Souagui', 'Tablat'],
 # 27 Mostaganem (10)
 'dz:wilaya:mostaganem': ['Achaacha', 'Aïn Nouïssy', 'Aïn Tedles', 'Bouguirat', 'Hassi Maameche', 'Kheireddine', 'Mesra', 'Mostaganem', 'Sidi Ali', 'Sidi Lakhdar'],
 # 28 M'Sila (7)
 'dz:wilaya:m-sila': ['Aïn El Hadjel', 'Chellal', 'Hammam Dhalaa', "M'Sila", 'Magra', 'Ouled Derradj', 'Sidi Aïssa'],
 # 29 Mascara (16)
 'dz:wilaya:mascara': ['Aouf', 'Aïn Fares', 'Aïn Fekan', 'Bou Hanifia', 'El Bordj', 'Ghriss', 'Hachem', 'Mascara', 'Mohammadia', 'Oggaz', 'Oued El Abtal', 'Oued Taria', 'Sig', 'Tighennif', 'Tizi', 'Zahana'],
 # 30 Ouargla (5)
 'dz:wilaya:ouargla': ['El Borma', 'Hassi Messaoud', "N'Goussa", 'Ouargla', 'Sidi Khouiled'],
 # 31 Oran (9)
 'dz:wilaya:oran': ['Arzew', 'Aïn El Turk', 'Bethioua', 'Bir El Djir', 'Boutlelis', 'Es Senia', 'Gdyel', 'Oran', 'Oued Tlelat'],
 # 32 El Bayadh (5)
 'dz:wilaya:el-bayadh': ['Boualem', 'Bougtoub', 'Brézina', 'El Bayadh', 'Rogassa'],
 # 33 Illizi (2)
 'dz:wilaya:illizi': ['Illizi', 'In Amenas'],
 # 34 Bordj Bou Arréridj (10)
 'dz:wilaya:bordj-bou-arreridj': ['Aïn Taghrout', 'Bir Kasdali', 'Bordj Bou Arréridj', 'Bordj Ghedir', 'Bordj Zemoura', 'Djaafra', 'El Hamadia', 'Mansoura', 'Medjana', 'Ras El Oued'],
 # 35 Boumerdès (9)
 'dz:wilaya:boumerdes': ['Baghlia', 'Bordj Menaiel', 'Boudouaou', 'Boumerdès', 'Dellys', 'Isser', 'Khemis El Khechna', 'Naciria', 'Thenia'],
 # 36 El Tarf (7)
 'dz:wilaya:el-tarf': ["Ben M'Hidi", 'Besbes', 'Bouhadjar', 'Bouteldja', 'Drean', 'El Kala', 'El Tarf'],
 # 37 Tindouf (1)
 'dz:wilaya:tindouf': ['Tindouf'],
 # 38 Tissemsilt (8)
 'dz:wilaya:tissemsilt': ['Ammari', 'Bordj Bou Naama', 'Bordj El Emir Abdelkader', 'Khemisti', 'Lardjem', 'Lazharia', 'Theniet El Had', 'Tissemsilt'],
 # 39 El Oued (10)
 'dz:wilaya:el-oued': ['Bayadha', 'Debila', 'El Oued', 'Guemar', 'Hassi Khalifa', 'Magrane', 'Mih Ouansa', 'Reguiba', 'Robbah', 'Taleb Larbi'],
 # 40 Khenchela (8)
 'dz:wilaya:khenchela': ['Aïn Touila', 'Babar', 'Bouhmama', 'Chechar', 'El Hamma', 'Kais', 'Khenchela', 'Ouled Rechache'],
 # 41 Souk Ahras (10)
 'dz:wilaya:souk-ahras': ['Bir Bou Haouch', 'Heddada', "M'daourouch", 'Mechroha', 'Merahna', 'Ouled Driss', 'Oum El Adhaim', 'Sedrata', 'Souk Ahras', 'Taoura'],
 # 42 Tipasa (10)
 'dz:wilaya:tipasa': ['Ahmar El Ain', 'Bou Ismail', 'Cherchell', 'Damous', 'Fouka', 'Gouraya', 'Hadjout', 'Kolea', 'Sidi Amar', 'Tipaza'],
 # 43 Mila (13)
 'dz:wilaya:mila': ['Aïn Beida Harriche', 'Bouhatem', 'Chelghoum Laid', 'Ferdjioua', 'Grarem Gouga', 'Mila', 'Oued Endja', 'Rouached', 'Sidi Merouane', 'Tadjenanet', 'Tassadane Haddada', 'Teleghma', 'Terrai Bainen'],
 # 44 Aïn Defla (14)
 'dz:wilaya:ain-defla': ['Aïn Defla', 'Aïn Lechiakh', 'Bathia', 'Bordj Emir Khaled', 'Boumedfaa', 'Djelida', 'Djendel', 'El Abadia', 'El Amra', 'El Attaf', 'Hammam Righa', 'Khemis Miliana', 'Miliana', 'Rouina'],
 # 45 Naama (7)
 'dz:wilaya:naama': ['Assela', 'Aïn Sefra', 'Mecheria', 'Mekmen Ben Amar', 'Moghrar', 'Naama', 'Sfissifa'],
 # 46 Aïn Témouchent (8)
 'dz:wilaya:ain-temouchent': ['Aïn El Arbaa', 'Aïn Kihal', 'Aïn Témouchent', 'Béni Saf', 'El Amria', 'El Malah', 'Hammam Bou Hadjar', 'Oulhaca Gheraba'],
 # 47 Ghardaïa (8)
 'dz:wilaya:ghardaia': ['Berriane', 'Bounoura', 'Dhayet Ben Dhaoua', 'El Guerrara', 'Ghardaïa', 'Mansoura', 'Metlili', 'Zelfana'],
 # 48 Relizane (13)
 'dz:wilaya:relizane': ['Ammi Moussa', 'Aïn Tarek', 'Djidioua', 'El Hamadna', 'El Matmar', 'Mazouna', 'Mendes', 'Oued Rhiou', 'Ramka', 'Relizane', "Sidi M'Hamed Ben Ali", 'Yellel', 'Zemmora'],
 # 49 Timimoun (4)
 'dz:wilaya:timimoun': ['Aougrout', 'Charouine', 'Timimoun', 'Tinerkouk'],
 # 50 Bordj Badji Mokhtar (1)
 'dz:wilaya:bordj-badji-mokhtar': ['Bordj Badji Mokhtar'],
 # 51 Ouled Djellal (2)
 'dz:wilaya:ouled-djellal': ['Ouled Djellal', 'Sidi Khaled'],
 # 52 Béni Abbès (6)
 'dz:wilaya:beni-abbes': ['Béni Abbès', 'El Ouata', 'Igli', 'Kerzaz', 'Ouled Khodeir', 'Tabelbala'],
 # 53 In Salah (2)
 'dz:wilaya:in-salah': ['In Ghar', 'In Salah'],
 # 54 In Guezzam (2)
 'dz:wilaya:in-guezzam': ['In Guezzam', 'Tin Zaouatine'],
 # 55 Touggourt (5)
 'dz:wilaya:touggourt': ['El Hadjira', 'Megarine', 'Taibet', 'Tamacine', 'Touggourt'],
 # 56 Djanet (1)
 'dz:wilaya:djanet': ['Djanet'],
 # 57 El M'ghair (2)
 'dz:wilaya:el-m-ghair': ['Djamaa', "El M'ghair"],
 # 58 El Menia (1)
 'dz:wilaya:el-menia': ['El Meniaa'],
 # 59 Aflou (5)
 'dz:wilaya:aflou': ['Aflou', 'Brida', 'El Ghicha', 'Gueltat Sidi Saad', 'Oued Morra'],
 # 60 Barika (3)
 'dz:wilaya:barika': ['Barika', 'Djezzar', 'Seggana'],
 # 61 El Kantara (3)
 'dz:wilaya:el-kantara': ['Djemourah', 'El Kantara', 'El Outaya'],
 # 62 Bir El Ater (2)
 'dz:wilaya:bir-el-ater': ['Bir El Ater', 'Negrine'],
 # 63 El Aricha (2)
 'dz:wilaya:el-aricha': ['El Aricha', 'Sidi Djillali'],
 # 64 Ksar Chellala (2)
 'dz:wilaya:ksar-chellala': ['Hamadia', 'Ksar Chellala'],
 # 65 Aïn Ouessara (4)
 'dz:wilaya:ain-ouessara': ['Aïn Ouessara', 'Birine', 'Had Sahary', 'Sidi Ladjel'],
 # 66 Messaad (2)
 'dz:wilaya:messaad': ['Faïdh El Botma', 'Messaad'],
 # 67 Ksar El Boukhari (6)
 'dz:wilaya:ksar-el-boukhari': ['Aziz', 'Aïn Boucif', 'Chahbounia', 'Chelalet El Adhaoura', 'Ksar El Boukhari', 'Ouled Antar'],
 # 68 Bou Saâda (8)
 'dz:wilaya:bou-saada': ['Aïn El Meleh', 'Ben Srour', 'Bou Saada', 'Djebel Messaad', 'Khoubana', 'Medjedel', 'Ouled Sidi Brahim', 'Sidi Ameur'],
 # 69 El Abiodh Sidi Cheikh (3)
 'dz:wilaya:el-abiodh-sidi-cheikh': ['Boussemghoun', 'Chellala', 'El Abiodh Sidi Cheikh'],
}
ok = True
for gov, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == gov)
    if have != sorted(names):
        print('FAIL gov', gov, have); fails.append(f'gov {gov}'); ok = False
if ok: print('PASS all 69 wilaya mappings (548 dairas)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 3908 codes / 3908 links (post-fix) ---
check('codes-3908', len(codes) == 3908, str(len(codes)))
check('links-3908', len(links) == 3908, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
cset = {c['code'] for c in codes}
lset = {l['postcode'] for l in links}
check('codes-linked-both-ways', cset == lset,
      f'unlinked={sorted(cset - lset)[:3]} dangling={sorted(lset - cset)[:3]}')
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 3908 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in counts.items() if c > 1)
check('multis-0', multis == [], str(multis))
lvl = Counter(byid[l['area_source_id']]['level'] for l in links)
check('links-all-L2', lvl == {'2': 3908}, str(lvl))
# Prefix check: 01-58 exact; 59-69 carry mother-wilaya prefixes (194 links).
mom = {'59': '03', '60': '05', '61': '07', '62': '12', '63': '13',
       '64': '14', '65': '17', '66': '17', '67': '26', '68': '28', '69': '32'}
exp_mm = {'59': 23, '60': 14, '61': 13, '62': 9, '63': 6, '64': 9,
          '65': 18, '66': 18, '67': 28, '68': 42, '69': 14}
mm = Counter()
badpre = []
for l in links:
    a = byid[l['area_source_id']]; w = byid[a['parent_source_id']]
    if l['postcode'][:2] != w['code']:
        mm[w['code']] += 1
        if mom.get(w['code']) != l['postcode'][:2]: badpre.append(l['postcode'])
check('mother-prefix-only', not badpre, str(badpre[:5]))
check('mother-prefix-counts', dict(mm) == exp_mm, str(dict(mm)))
pin = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
# 19 retarget pins (each: geoalgeria + frwiki/JO/OSM + GN place).
check('pin-03013-gueltat-sidi-saad', pin.get('03013') == 'dz:daira:aflou:gueltat-sidi-saad')
check('pin-03024-aflou', pin.get('03024') == 'dz:daira:aflou:aflou')
check('pin-03027-oued-morra', pin.get('03027') == 'dz:daira:aflou:oued-morra')
check('pin-03030-aflou', pin.get('03030') == 'dz:daira:aflou:aflou')
check('pin-03031-aflou', pin.get('03031') == 'dz:daira:aflou:aflou')
check('pin-03034-aflou', pin.get('03034') == 'dz:daira:aflou:aflou')
check('pin-03041-oued-morra', pin.get('03041') == 'dz:daira:aflou:oued-morra')
check('pin-05046-djezzar', pin.get('05046') == 'dz:daira:barika:djezzar')
check('pin-12043-bir-el-ater', pin.get('12043') == 'dz:daira:bir-el-ater:bir-el-ater')
check('pin-12044-negrine', pin.get('12044') == 'dz:daira:bir-el-ater:negrine')
check('pin-12045-bir-el-ater', pin.get('12045') == 'dz:daira:bir-el-ater:bir-el-ater')
check('pin-14018-hamadia', pin.get('14018') == 'dz:daira:ksar-chellala:hamadia')
check('pin-17021-faidh-el-botma', pin.get('17021') == 'dz:daira:messaad:faidh-el-botma')
check('pin-17040-had-sahary', pin.get('17040') == 'dz:daira:ain-ouessara:had-sahary')
check('pin-17043-birine', pin.get('17043') == 'dz:daira:ain-ouessara:birine')
check('pin-17047-birine', pin.get('17047') == 'dz:daira:ain-ouessara:birine')
check('pin-17051-sidi-ladjel', pin.get('17051') == 'dz:daira:ain-ouessara:sidi-ladjel')
check('pin-17061-faidh-el-botma', pin.get('17061') == 'dz:daira:messaad:faidh-el-botma')
check('pin-26058-tablat', pin.get('26058') == 'dz:daira:medea:tablat')
# Deliberate keeps: BOD/Debdeb/Ain Smara are communes (frwiki+ONIL), not dairas.
check('keep-25006', pin.get('25006') == 'dz:daira:constantine:el-khroub')
check('keep-25054', pin.get('25054') == 'dz:daira:constantine:el-khroub')
check('keep-25064', pin.get('25064') == 'dz:daira:constantine:el-khroub')
check('keep-33003', pin.get('33003') == 'dz:daira:illizi:in-amenas')
check('keep-33004', pin.get('33004') == 'dz:daira:illizi:in-amenas')
check('keep-33015', pin.get('33015') == 'dz:daira:illizi:in-amenas')
check('keep-33023', pin.get('33023') == 'dz:daira:illizi:in-amenas')
check('keep-33024', pin.get('33024') == 'dz:daira:illizi:in-amenas')
# Seat anchors (UPU dzaEn 16027 ALGIERS + GN-corroborated seats).
check('seat-01000', pin.get('01000') == 'dz:daira:adrar:adrar', pin.get('01000'))
check('seat-03000', pin.get('03000') == 'dz:daira:laghouat:laghouat', pin.get('03000'))
check('seat-06000', pin.get('06000') == 'dz:daira:bejaia:bejaia', pin.get('06000'))
check('seat-15000', pin.get('15000') == 'dz:daira:tizi-ouzou:tizi-ouzou', pin.get('15000'))
check('seat-16027', pin.get('16027') == 'dz:daira:alger:baraki', pin.get('16027'))
check('seat-17000', pin.get('17000') == 'dz:daira:djelfa:djelfa', pin.get('17000'))
check('seat-19000', pin.get('19000') == 'dz:daira:setif:setif', pin.get('19000'))
check('seat-23000', pin.get('23000') == 'dz:daira:annaba:annaba', pin.get('23000'))
check('seat-25000', pin.get('25000') == 'dz:daira:constantine:constantine', pin.get('25000'))
check('seat-26000', pin.get('26000') == 'dz:daira:medea:medea', pin.get('26000'))
check('seat-28000', pin.get('28000') == 'dz:daira:m-sila:m-sila', pin.get('28000'))
check('seat-30025', pin.get('30025') == 'dz:daira:ouargla:el-borma', pin.get('30025'))
check('seat-31000', pin.get('31000') == 'dz:daira:oran:oran', pin.get('31000'))
# Deliberate absences: renumbered-olds (153) + GN-only unverifiable (125).
check('no-old-01001', '01001' not in cset)
check('no-old-30002', '30002' not in cset)
check('no-old-08028', '08028' not in cset)
check('no-gnonly-02800', '02800' not in cset)
check('no-gnonly-03029', '03029' not in cset)
check('no-gnonly-08800', '08800' not in cset)
# Per-area primary counts (548/548 covered post-fix).
exp_counts = {
 'dz:daira:adrar:adrar': 18,
 'dz:daira:adrar:aoulef': 6,
 'dz:daira:adrar:fenoughil': 7,
 'dz:daira:adrar:reggane': 9,
 'dz:daira:adrar:tsabit': 4,
 'dz:daira:adrar:zaouiet-kounta': 7,
 'dz:daira:aflou:aflou': 10,
 'dz:daira:aflou:brida': 3,
 'dz:daira:aflou:el-ghicha': 3,
 'dz:daira:aflou:gueltat-sidi-saad': 5,
 'dz:daira:aflou:oued-morra': 2,
 'dz:daira:ain-defla:ain-defla': 12,
 'dz:daira:ain-defla:ain-lechiakh': 3,
 'dz:daira:ain-defla:bathia': 1,
 'dz:daira:ain-defla:bordj-emir-khaled': 1,
 'dz:daira:ain-defla:boumedfaa': 3,
 'dz:daira:ain-defla:djelida': 3,
 'dz:daira:ain-defla:djendel': 7,
 'dz:daira:ain-defla:el-abadia': 2,
 'dz:daira:ain-defla:el-amra': 6,
 'dz:daira:ain-defla:el-attaf': 8,
 'dz:daira:ain-defla:hammam-righa': 5,
 'dz:daira:ain-defla:khemis-miliana': 8,
 'dz:daira:ain-defla:miliana': 6,
 'dz:daira:ain-defla:rouina': 6,
 'dz:daira:ain-ouessara:ain-ouessara': 7,
 'dz:daira:ain-ouessara:birine': 4,
 'dz:daira:ain-ouessara:had-sahary': 4,
 'dz:daira:ain-ouessara:sidi-ladjel': 3,
 'dz:daira:ain-temouchent:ain-el-arbaa': 7,
 'dz:daira:ain-temouchent:ain-kihal': 7,
 'dz:daira:ain-temouchent:ain-temouchent': 13,
 'dz:daira:ain-temouchent:beni-saf': 9,
 'dz:daira:ain-temouchent:el-amria': 12,
 'dz:daira:ain-temouchent:el-malah': 11,
 'dz:daira:ain-temouchent:hammam-bou-hadjar': 11,
 'dz:daira:ain-temouchent:oulhaca-gheraba': 4,
 'dz:daira:alger:bab-el-oued': 15,
 'dz:daira:alger:baraki': 14,
 'dz:daira:alger:bir-mourad-rais': 18,
 'dz:daira:alger:birtouta': 7,
 'dz:daira:alger:bouzareah': 17,
 'dz:daira:alger:cheraga': 19,
 'dz:daira:alger:dar-el-beida': 26,
 'dz:daira:alger:draria': 14,
 'dz:daira:alger:el-harrach': 11,
 'dz:daira:alger:hussein-dey': 17,
 'dz:daira:alger:rouiba': 8,
 'dz:daira:alger:sidi-m-hamed': 26,
 'dz:daira:alger:zeralda': 22,
 'dz:daira:annaba:ain-berda': 10,
 'dz:daira:annaba:annaba': 17,
 'dz:daira:annaba:berrahal': 9,
 'dz:daira:annaba:chetaibi': 3,
 'dz:daira:annaba:el-bouni': 13,
 'dz:daira:annaba:el-hadjar': 10,
 'dz:daira:barika:barika': 7,
 'dz:daira:barika:djezzar': 4,
 'dz:daira:barika:seggana': 3,
 'dz:daira:batna:ain-djasser': 2,
 'dz:daira:batna:ain-touta': 9,
 'dz:daira:batna:arris': 5,
 'dz:daira:batna:batna': 26,
 'dz:daira:batna:bouzina': 3,
 'dz:daira:batna:chemora': 4,
 'dz:daira:batna:elmadher': 5,
 'dz:daira:batna:ichmoul': 4,
 'dz:daira:batna:menaa': 8,
 'dz:daira:batna:merouana': 10,
 'dz:daira:batna:n-gaous': 7,
 'dz:daira:batna:ouled-si-slimane': 4,
 'dz:daira:batna:ras-el-aioun': 13,
 'dz:daira:batna:seriana': 6,
 'dz:daira:batna:t-kout': 7,
 'dz:daira:batna:tazoult': 4,
 'dz:daira:batna:teniet-el-abed': 8,
 'dz:daira:batna:timgad': 4,
 'dz:daira:bechar:abadla': 7,
 'dz:daira:bechar:bechar': 26,
 'dz:daira:bechar:beni-ounif': 3,
 'dz:daira:bechar:kenadsa': 3,
 'dz:daira:bechar:lahmar': 3,
 'dz:daira:bechar:taghit': 2,
 'dz:daira:bejaia:adekar': 7,
 'dz:daira:bejaia:akbou': 13,
 'dz:daira:bejaia:amizour': 14,
 'dz:daira:bejaia:aokas': 5,
 'dz:daira:bejaia:barbacha': 5,
 'dz:daira:bejaia:bejaia': 20,
 'dz:daira:bejaia:beni-maouche': 3,
 'dz:daira:bejaia:chemini': 7,
 'dz:daira:bejaia:darguina': 6,
 'dz:daira:bejaia:el-kseur': 9,
 'dz:daira:bejaia:ighil-ali': 5,
 'dz:daira:bejaia:kherrata': 8,
 'dz:daira:bejaia:ouzellaguen': 3,
 'dz:daira:bejaia:seddouk': 11,
 'dz:daira:bejaia:sidi-aich': 8,
 'dz:daira:bejaia:souk-el-tenine': 6,
 'dz:daira:bejaia:tazmalt': 6,
 'dz:daira:bejaia:tichy': 4,
 'dz:daira:bejaia:timezrit': 7,
 'dz:daira:beni-abbes:beni-abbes': 5,
 'dz:daira:beni-abbes:el-ouata': 5,
 'dz:daira:beni-abbes:igli': 2,
 'dz:daira:beni-abbes:kerzaz': 4,
 'dz:daira:beni-abbes:ouled-khodeir': 3,
 'dz:daira:beni-abbes:tabelbala': 1,
 'dz:daira:bir-el-ater:bir-el-ater': 7,
 'dz:daira:bir-el-ater:negrine': 2,
 'dz:daira:biskra:biskra': 18,
 'dz:daira:biskra:foughala': 3,
 'dz:daira:biskra:m-chouneche': 3,
 'dz:daira:biskra:ourlal': 7,
 'dz:daira:biskra:sidi-okba': 11,
 'dz:daira:biskra:tolga': 7,
 'dz:daira:biskra:zeribet-el-oued': 13,
 'dz:daira:blida:blida': 15,
 'dz:daira:blida:boufarik': 8,
 'dz:daira:blida:bougara': 8,
 'dz:daira:blida:bouinan': 7,
 'dz:daira:blida:el-affroun': 5,
 'dz:daira:blida:larbaa': 4,
 'dz:daira:blida:meftah': 5,
 'dz:daira:blida:mouzaia': 10,
 'dz:daira:blida:oued-alleug': 8,
 'dz:daira:blida:ouled-yaich': 9,
 'dz:daira:bordj-badji-mokhtar:bordj-badji-mokhtar': 3,
 'dz:daira:bordj-bou-arreridj:ain-taghrout': 2,
 'dz:daira:bordj-bou-arreridj:bir-kasdali': 7,
 'dz:daira:bordj-bou-arreridj:bordj-bou-arreridj': 10,
 'dz:daira:bordj-bou-arreridj:bordj-ghedir': 9,
 'dz:daira:bordj-bou-arreridj:bordj-zemoura': 5,
 'dz:daira:bordj-bou-arreridj:djaafra': 7,
 'dz:daira:bordj-bou-arreridj:el-hamadia': 6,
 'dz:daira:bordj-bou-arreridj:mansoura': 12,
 'dz:daira:bordj-bou-arreridj:medjana': 10,
 'dz:daira:bordj-bou-arreridj:ras-el-oued': 10,
 'dz:daira:bou-saada:ain-el-meleh': 11,
 'dz:daira:bou-saada:ben-srour': 5,
 'dz:daira:bou-saada:bou-saada': 10,
 'dz:daira:bou-saada:djebel-messaad': 2,
 'dz:daira:bou-saada:khoubana': 5,
 'dz:daira:bou-saada:medjedel': 3,
 'dz:daira:bou-saada:ouled-sidi-brahim': 4,
 'dz:daira:bou-saada:sidi-ameur': 2,
 'dz:daira:bouira:ain-bessem': 6,
 'dz:daira:bouira:bechloul': 9,
 'dz:daira:bouira:bir-ghbalou': 4,
 'dz:daira:bouira:bordj-okhriss': 6,
 'dz:daira:bouira:bouira': 19,
 'dz:daira:bouira:el-hachimia': 4,
 'dz:daira:bouira:haizer': 6,
 'dz:daira:bouira:kadiria': 9,
 'dz:daira:bouira:lakhdaria': 14,
 'dz:daira:bouira:m-chedallah': 16,
 'dz:daira:bouira:souk-el-khemis': 2,
 'dz:daira:bouira:sour-el-ghozlane': 10,
 'dz:daira:boumerdes:baghlia': 7,
 'dz:daira:boumerdes:bordj-menaiel': 13,
 'dz:daira:boumerdes:boudouaou': 11,
 'dz:daira:boumerdes:boumerdes': 8,
 'dz:daira:boumerdes:dellys': 8,
 'dz:daira:boumerdes:isser': 9,
 'dz:daira:boumerdes:khemis-el-khechna': 8,
 'dz:daira:boumerdes:naciria': 3,
 'dz:daira:boumerdes:thenia': 6,
 'dz:daira:chlef:abou-el-hassen': 6,
 'dz:daira:chlef:ain-merane': 6,
 'dz:daira:chlef:beni-haoua': 3,
 'dz:daira:chlef:boukadir': 11,
 'dz:daira:chlef:chlef': 22,
 'dz:daira:chlef:el-karimia': 6,
 'dz:daira:chlef:el-marsa': 3,
 'dz:daira:chlef:oued-fodda': 8,
 'dz:daira:chlef:ouled-ben-abdelkader': 4,
 'dz:daira:chlef:ouled-fares': 10,
 'dz:daira:chlef:taougrite': 6,
 'dz:daira:chlef:tenes': 8,
 'dz:daira:chlef:zeboudja': 9,
 'dz:daira:constantine:ain-abid': 8,
 'dz:daira:constantine:constantine': 22,
 'dz:daira:constantine:el-khroub': 21,
 'dz:daira:constantine:hamma-bouziane': 11,
 'dz:daira:constantine:ibn-ziad': 4,
 'dz:daira:constantine:zighoud-youcef': 4,
 'dz:daira:djanet:djanet': 7,
 'dz:daira:djelfa:ain-el-ibel': 10,
 'dz:daira:djelfa:charef': 6,
 'dz:daira:djelfa:dar-chioukh': 8,
 'dz:daira:djelfa:djelfa': 16,
 'dz:daira:djelfa:el-idrissia': 4,
 'dz:daira:djelfa:hassi-bahbah': 8,
 'dz:daira:el-abiodh-sidi-cheikh:boussemghoun': 1,
 'dz:daira:el-abiodh-sidi-cheikh:chellala': 4,
 'dz:daira:el-abiodh-sidi-cheikh:el-abiodh-sidi-cheikh': 9,
 'dz:daira:el-aricha:el-aricha': 3,
 'dz:daira:el-aricha:sidi-djillali': 3,
 'dz:daira:el-bayadh:boualem': 5,
 'dz:daira:el-bayadh:bougtoub': 8,
 'dz:daira:el-bayadh:brezina': 4,
 'dz:daira:el-bayadh:el-bayadh': 11,
 'dz:daira:el-bayadh:rogassa': 3,
 'dz:daira:el-kantara:djemourah': 5,
 'dz:daira:el-kantara:el-kantara': 5,
 'dz:daira:el-kantara:el-outaya': 3,
 'dz:daira:el-m-ghair:djamaa': 13,
 'dz:daira:el-m-ghair:el-m-ghair': 10,
 'dz:daira:el-menia:el-meniaa': 6,
 'dz:daira:el-oued:bayadha': 4,
 'dz:daira:el-oued:debila': 10,
 'dz:daira:el-oued:el-oued': 17,
 'dz:daira:el-oued:guemar': 7,
 'dz:daira:el-oued:hassi-khalifa': 8,
 'dz:daira:el-oued:magrane': 7,
 'dz:daira:el-oued:mih-ouansa': 5,
 'dz:daira:el-oued:reguiba': 5,
 'dz:daira:el-oued:robbah': 8,
 'dz:daira:el-oued:taleb-larbi': 6,
 'dz:daira:el-tarf:ben-m-hidi': 10,
 'dz:daira:el-tarf:besbes': 7,
 'dz:daira:el-tarf:bouhadjar': 8,
 'dz:daira:el-tarf:bouteldja': 6,
 'dz:daira:el-tarf:drean': 8,
 'dz:daira:el-tarf:el-kala': 8,
 'dz:daira:el-tarf:el-tarf': 12,
 'dz:daira:ghardaia:berriane': 4,
 'dz:daira:ghardaia:bounoura': 7,
 'dz:daira:ghardaia:dhayet-ben-dhaoua': 3,
 'dz:daira:ghardaia:el-guerrara': 6,
 'dz:daira:ghardaia:ghardaia': 11,
 'dz:daira:ghardaia:mansoura': 1,
 'dz:daira:ghardaia:metlili': 7,
 'dz:daira:ghardaia:zelfana': 2,
 'dz:daira:guelma:ain-makhlouf': 6,
 'dz:daira:guelma:bouchegouf': 5,
 'dz:daira:guelma:guelaat-bou-sbaa': 3,
 'dz:daira:guelma:guelma': 14,
 'dz:daira:guelma:hammam-debagh': 8,
 'dz:daira:guelma:hammam-n-bails': 3,
 'dz:daira:guelma:heliopolis': 4,
 'dz:daira:guelma:houari-boumediene': 5,
 'dz:daira:guelma:khezarra': 3,
 'dz:daira:guelma:oued-zenati': 6,
 'dz:daira:illizi:illizi': 8,
 'dz:daira:illizi:in-amenas': 9,
 'dz:daira:in-guezzam:in-guezzam': 2,
 'dz:daira:in-guezzam:tin-zaouatine': 1,
 'dz:daira:in-salah:in-ghar': 1,
 'dz:daira:in-salah:in-salah': 10,
 'dz:daira:jijel:chekfa': 6,
 'dz:daira:jijel:djimla': 2,
 'dz:daira:jijel:el-ancer': 7,
 'dz:daira:jijel:el-aouana': 4,
 'dz:daira:jijel:el-milia': 5,
 'dz:daira:jijel:jijel': 10,
 'dz:daira:jijel:settara': 3,
 'dz:daira:jijel:sidi-maarouf': 3,
 'dz:daira:jijel:taher': 11,
 'dz:daira:jijel:texenna': 4,
 'dz:daira:jijel:ziama-mansouriah': 3,
 'dz:daira:khenchela:ain-touila': 5,
 'dz:daira:khenchela:babar': 4,
 'dz:daira:khenchela:bouhmama': 5,
 'dz:daira:khenchela:chechar': 9,
 'dz:daira:khenchela:el-hamma': 8,
 'dz:daira:khenchela:kais': 5,
 'dz:daira:khenchela:khenchela': 13,
 'dz:daira:khenchela:ouled-rechache': 5,
 'dz:daira:ksar-chellala:hamadia': 4,
 'dz:daira:ksar-chellala:ksar-chellala': 5,
 'dz:daira:ksar-el-boukhari:ain-boucif': 6,
 'dz:daira:ksar-el-boukhari:aziz': 4,
 'dz:daira:ksar-el-boukhari:chahbounia': 3,
 'dz:daira:ksar-el-boukhari:chelalet-el-adhaoura': 4,
 'dz:daira:ksar-el-boukhari:ksar-el-boukhari': 7,
 'dz:daira:ksar-el-boukhari:ouled-antar': 4,
 'dz:daira:laghouat:ain-madhi': 7,
 'dz:daira:laghouat:hassi-r-mel': 5,
 'dz:daira:laghouat:ksar-el-hirane': 2,
 'dz:daira:laghouat:laghouat': 14,
 'dz:daira:laghouat:sidi-makhlouf': 2,
 'dz:daira:m-sila:ain-el-hadjel': 2,
 'dz:daira:m-sila:chellal': 6,
 'dz:daira:m-sila:hammam-dhalaa': 10,
 'dz:daira:m-sila:m-sila': 14,
 'dz:daira:m-sila:magra': 6,
 'dz:daira:m-sila:ouled-derradj': 10,
 'dz:daira:m-sila:sidi-aissa': 6,
 'dz:daira:mascara:ain-fares': 3,
 'dz:daira:mascara:ain-fekan': 2,
 'dz:daira:mascara:aouf': 4,
 'dz:daira:mascara:bou-hanifia': 6,
 'dz:daira:mascara:el-bordj': 5,
 'dz:daira:mascara:ghriss': 9,
 'dz:daira:mascara:hachem': 6,
 'dz:daira:mascara:mascara': 13,
 'dz:daira:mascara:mohammadia': 15,
 'dz:daira:mascara:oggaz': 5,
 'dz:daira:mascara:oued-el-abtal': 6,
 'dz:daira:mascara:oued-taria': 3,
 'dz:daira:mascara:sig': 8,
 'dz:daira:mascara:tighennif': 9,
 'dz:daira:mascara:tizi': 5,
 'dz:daira:mascara:zahana': 5,
 'dz:daira:medea:beni-slimane': 4,
 'dz:daira:medea:berrouaghia': 6,
 'dz:daira:medea:el-azizia': 3,
 'dz:daira:medea:el-guelb-el-kebir': 4,
 'dz:daira:medea:el-omaria': 3,
 'dz:daira:medea:medea': 20,
 'dz:daira:medea:ouamri': 3,
 'dz:daira:medea:ouzera': 5,
 'dz:daira:medea:seghouane': 5,
 'dz:daira:medea:si-mahdjoub': 3,
 'dz:daira:medea:sidi-naamane': 4,
 'dz:daira:medea:souagui': 4,
 'dz:daira:medea:tablat': 4,
 'dz:daira:messaad:faidh-el-botma': 4,
 'dz:daira:messaad:messaad': 14,
 'dz:daira:mila:ain-beida-harriche': 3,
 'dz:daira:mila:bouhatem': 4,
 'dz:daira:mila:chelghoum-laid': 14,
 'dz:daira:mila:ferdjioua': 6,
 'dz:daira:mila:grarem-gouga': 6,
 'dz:daira:mila:mila': 12,
 'dz:daira:mila:oued-endja': 6,
 'dz:daira:mila:rouached': 5,
 'dz:daira:mila:sidi-merouane': 4,
 'dz:daira:mila:tadjenanet': 6,
 'dz:daira:mila:tassadane-haddada': 4,
 'dz:daira:mila:teleghma': 7,
 'dz:daira:mila:terrai-bainen': 5,
 'dz:daira:mostaganem:achaacha': 5,
 'dz:daira:mostaganem:ain-nouissy': 7,
 'dz:daira:mostaganem:ain-tedles': 8,
 'dz:daira:mostaganem:bouguirat': 7,
 'dz:daira:mostaganem:hassi-maameche': 6,
 'dz:daira:mostaganem:kheireddine': 6,
 'dz:daira:mostaganem:mesra': 6,
 'dz:daira:mostaganem:mostaganem': 15,
 'dz:daira:mostaganem:sidi-ali': 6,
 'dz:daira:mostaganem:sidi-lakhdar': 6,
 'dz:daira:naama:ain-sefra': 12,
 'dz:daira:naama:assela': 3,
 'dz:daira:naama:mecheria': 15,
 'dz:daira:naama:mekmen-ben-amar': 4,
 'dz:daira:naama:moghrar': 4,
 'dz:daira:naama:naama': 10,
 'dz:daira:naama:sfissifa': 3,
 'dz:daira:oran:ain-el-turk': 8,
 'dz:daira:oran:arzew': 7,
 'dz:daira:oran:bethioua': 13,
 'dz:daira:oran:bir-el-djir': 15,
 'dz:daira:oran:boutlelis': 9,
 'dz:daira:oran:es-senia': 18,
 'dz:daira:oran:gdyel': 8,
 'dz:daira:oran:oran': 29,
 'dz:daira:oran:oued-tlelat': 12,
 'dz:daira:ouargla:el-borma': 1,
 'dz:daira:ouargla:hassi-messaoud': 7,
 'dz:daira:ouargla:n-goussa': 3,
 'dz:daira:ouargla:ouargla': 19,
 'dz:daira:ouargla:sidi-khouiled': 5,
 'dz:daira:ouled-djellal:ouled-djellal': 10,
 'dz:daira:ouled-djellal:sidi-khaled': 9,
 'dz:daira:oum-el-bouaghi:ain-babouche': 2,
 'dz:daira:oum-el-bouaghi:ain-beida': 12,
 'dz:daira:oum-el-bouaghi:ain-fakroun': 5,
 'dz:daira:oum-el-bouaghi:ain-kercha': 5,
 'dz:daira:oum-el-bouaghi:ain-m-lila': 8,
 'dz:daira:oum-el-bouaghi:dhalaa': 2,
 'dz:daira:oum-el-bouaghi:fkirina': 2,
 'dz:daira:oum-el-bouaghi:ksar-sbahi': 1,
 'dz:daira:oum-el-bouaghi:meskiana': 6,
 'dz:daira:oum-el-bouaghi:oum-el-bouaghi': 12,
 'dz:daira:oum-el-bouaghi:sigus': 7,
 'dz:daira:oum-el-bouaghi:souk-naamane': 4,
 'dz:daira:relizane:ain-tarek': 4,
 'dz:daira:relizane:ammi-moussa': 4,
 'dz:daira:relizane:djidioua': 4,
 'dz:daira:relizane:el-hamadna': 5,
 'dz:daira:relizane:el-matmar': 5,
 'dz:daira:relizane:mazouna': 6,
 'dz:daira:relizane:mendes': 4,
 'dz:daira:relizane:oued-rhiou': 10,
 'dz:daira:relizane:ramka': 2,
 'dz:daira:relizane:relizane': 15,
 'dz:daira:relizane:sidi-m-hamed-ben-ali': 5,
 'dz:daira:relizane:yellel': 6,
 'dz:daira:relizane:zemmora': 5,
 'dz:daira:saida:ain-el-hadjar': 11,
 'dz:daira:saida:el-hassasna': 6,
 'dz:daira:saida:ouled-brahim': 7,
 'dz:daira:saida:saida': 13,
 'dz:daira:saida:sidi-boubekeur': 11,
 'dz:daira:saida:youb': 7,
 'dz:daira:setif:ain-arnat': 15,
 'dz:daira:setif:ain-azel': 9,
 'dz:daira:setif:ain-el-kebira': 6,
 'dz:daira:setif:ain-oulmene': 9,
 'dz:daira:setif:amoucha': 6,
 'dz:daira:setif:babor': 3,
 'dz:daira:setif:beni-aziz': 5,
 'dz:daira:setif:beni-ourtilane': 10,
 'dz:daira:setif:bir-el-arch': 4,
 'dz:daira:setif:bouandas': 7,
 'dz:daira:setif:bougaa': 4,
 'dz:daira:setif:djemila': 5,
 'dz:daira:setif:el-eulma': 11,
 'dz:daira:setif:guenzet': 4,
 'dz:daira:setif:guidjel': 5,
 'dz:daira:setif:hammam-guergour': 5,
 'dz:daira:setif:hammam-soukhna': 4,
 'dz:daira:setif:maoklane': 3,
 'dz:daira:setif:salah-bey': 11,
 'dz:daira:setif:setif': 19,
 'dz:daira:sidi-bel-abbes:ain-el-berd': 7,
 'dz:daira:sidi-bel-abbes:ben-badis': 7,
 'dz:daira:sidi-bel-abbes:marhoum': 3,
 'dz:daira:sidi-bel-abbes:merine': 5,
 'dz:daira:sidi-bel-abbes:mostefa-ben-brahim': 4,
 'dz:daira:sidi-bel-abbes:moulay-slissen': 4,
 'dz:daira:sidi-bel-abbes:ras-el-ma': 5,
 'dz:daira:sidi-bel-abbes:sfisef': 7,
 'dz:daira:sidi-bel-abbes:sidi-ali-benyoub': 6,
 'dz:daira:sidi-bel-abbes:sidi-ali-boussidi': 4,
 'dz:daira:sidi-bel-abbes:sidi-bel-abbes': 19,
 'dz:daira:sidi-bel-abbes:sidi-lahcene': 6,
 'dz:daira:sidi-bel-abbes:telagh': 7,
 'dz:daira:sidi-bel-abbes:tenira': 7,
 'dz:daira:sidi-bel-abbes:tessala': 3,
 'dz:daira:skikda:ain-kechra': 4,
 'dz:daira:skikda:azzaba': 12,
 'dz:daira:skikda:ben-azzouz': 8,
 'dz:daira:skikda:collo': 8,
 'dz:daira:skikda:el-hadaiek': 3,
 'dz:daira:skikda:el-harrouch': 11,
 'dz:daira:skikda:ouled-attia': 5,
 'dz:daira:skikda:oum-toub': 2,
 'dz:daira:skikda:ramdane-djamel': 3,
 'dz:daira:skikda:sidi-mezghiche': 4,
 'dz:daira:skikda:skikda': 15,
 'dz:daira:skikda:tamalous': 10,
 'dz:daira:skikda:zitouna': 2,
 'dz:daira:souk-ahras:bir-bou-haouch': 3,
 'dz:daira:souk-ahras:heddada': 4,
 'dz:daira:souk-ahras:m-daourouch': 6,
 'dz:daira:souk-ahras:mechroha': 5,
 'dz:daira:souk-ahras:merahna': 6,
 'dz:daira:souk-ahras:ouled-driss': 3,
 'dz:daira:souk-ahras:oum-el-adhaim': 4,
 'dz:daira:souk-ahras:sedrata': 7,
 'dz:daira:souk-ahras:souk-ahras': 14,
 'dz:daira:souk-ahras:taoura': 8,
 'dz:daira:tamanghasset:abalessa': 3,
 'dz:daira:tamanghasset:tamanrasset': 14,
 'dz:daira:tamanghasset:tazrouk': 6,
 'dz:daira:tebessa:bir-mokkadem': 5,
 'dz:daira:tebessa:cheria': 5,
 'dz:daira:tebessa:el-aouinet': 5,
 'dz:daira:tebessa:el-kouif': 6,
 'dz:daira:tebessa:el-ma-labiod': 4,
 'dz:daira:tebessa:el-ogla': 5,
 'dz:daira:tebessa:morsott': 4,
 'dz:daira:tebessa:ouenza': 9,
 'dz:daira:tebessa:oum-ali': 2,
 'dz:daira:tebessa:tebessa': 17,
 'dz:daira:tiaret:ain-deheb': 4,
 'dz:daira:tiaret:ain-kermes': 7,
 'dz:daira:tiaret:dahmouni': 4,
 'dz:daira:tiaret:frenda': 8,
 'dz:daira:tiaret:mahdia': 7,
 'dz:daira:tiaret:mechraa-sfa': 4,
 'dz:daira:tiaret:medroussa': 3,
 'dz:daira:tiaret:meghila': 3,
 'dz:daira:tiaret:oued-lili': 4,
 'dz:daira:tiaret:rahouia': 3,
 'dz:daira:tiaret:sougueur': 7,
 'dz:daira:tiaret:tiaret': 18,
 'dz:daira:timimoun:aougrout': 6,
 'dz:daira:timimoun:charouine': 7,
 'dz:daira:timimoun:timimoun': 11,
 'dz:daira:timimoun:tinerkouk': 4,
 'dz:daira:tindouf:tindouf': 20,
 'dz:daira:tipasa:ahmar-el-ain': 6,
 'dz:daira:tipasa:bou-ismail': 6,
 'dz:daira:tipasa:cherchell': 9,
 'dz:daira:tipasa:damous': 5,
 'dz:daira:tipasa:fouka': 7,
 'dz:daira:tipasa:gouraya': 5,
 'dz:daira:tipasa:hadjout': 8,
 'dz:daira:tipasa:kolea': 10,
 'dz:daira:tipasa:sidi-amar': 6,
 'dz:daira:tipasa:tipaza': 6,
 'dz:daira:tissemsilt:ammari': 3,
 'dz:daira:tissemsilt:bordj-bou-naama': 5,
 'dz:daira:tissemsilt:bordj-el-emir-abdelkader': 3,
 'dz:daira:tissemsilt:khemisti': 8,
 'dz:daira:tissemsilt:lardjem': 5,
 'dz:daira:tissemsilt:lazharia': 4,
 'dz:daira:tissemsilt:theniet-el-had': 4,
 'dz:daira:tissemsilt:tissemsilt': 15,
 'dz:daira:tizi-ouzou:ain-el-hammam': 10,
 'dz:daira:tizi-ouzou:azazga': 13,
 'dz:daira:tizi-ouzou:azeffoun': 6,
 'dz:daira:tizi-ouzou:beni-douala': 7,
 'dz:daira:tizi-ouzou:beni-yenni': 5,
 'dz:daira:tizi-ouzou:boghni': 7,
 'dz:daira:tizi-ouzou:bouzeguene': 7,
 'dz:daira:tizi-ouzou:draa-ben-khedda': 8,
 'dz:daira:tizi-ouzou:draa-el-mizan': 12,
 'dz:daira:tizi-ouzou:iferhounene': 4,
 'dz:daira:tizi-ouzou:larbaa-nath-irathen': 8,
 'dz:daira:tizi-ouzou:maatkas': 5,
 'dz:daira:tizi-ouzou:makouda': 7,
 'dz:daira:tizi-ouzou:mekla': 8,
 'dz:daira:tizi-ouzou:ouacif': 3,
 'dz:daira:tizi-ouzou:ouadhia': 8,
 'dz:daira:tizi-ouzou:ouaguenoun': 10,
 'dz:daira:tizi-ouzou:tigzirt': 9,
 'dz:daira:tizi-ouzou:tizi-gheniff': 7,
 'dz:daira:tizi-ouzou:tizi-ouzou': 13,
 'dz:daira:tizi-ouzou:tizi-rached': 4,
 'dz:daira:tlemcen:ain-tallout': 4,
 'dz:daira:tlemcen:bab-el-assa': 4,
 'dz:daira:tlemcen:beni-boussaid': 3,
 'dz:daira:tlemcen:beni-snous': 5,
 'dz:daira:tlemcen:bensekrane': 3,
 'dz:daira:tlemcen:chetouane': 8,
 'dz:daira:tlemcen:fellaoucene': 4,
 'dz:daira:tlemcen:ghazaouet': 8,
 'dz:daira:tlemcen:hennaya': 5,
 'dz:daira:tlemcen:honaine': 3,
 'dz:daira:tlemcen:maghnia': 10,
 'dz:daira:tlemcen:mansourah': 8,
 'dz:daira:tlemcen:marsa-ben-m-hidi': 3,
 'dz:daira:tlemcen:nedroma': 5,
 'dz:daira:tlemcen:ouled-mimoun': 4,
 'dz:daira:tlemcen:remchi': 8,
 'dz:daira:tlemcen:sabra': 3,
 'dz:daira:tlemcen:sebdou': 4,
 'dz:daira:tlemcen:tlemcen': 12,
 'dz:daira:touggourt:el-hadjira': 6,
 'dz:daira:touggourt:megarine': 6,
 'dz:daira:touggourt:taibet': 6,
 'dz:daira:touggourt:tamacine': 4,
 'dz:daira:touggourt:touggourt': 11,
}
got_counts = Counter(l['area_source_id'] for l in links if l['is_primary'] == 'true')
badc = {k: (got_counts.get(k, 0), v) for k, v in exp_counts.items() if got_counts.get(k, 0) != v}
check('per-area-counts-548', not badc, str(dict(list(badc.items())[:3])))
print('FAILURES:', fails) if fails else print('ALL PASS')
sys.exit(1 if fails else 0)
