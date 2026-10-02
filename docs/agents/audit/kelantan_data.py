# (district, slug, name, type) — UPI 2023 book + GIS corroborated
ADDS = [
# Kota Bharu 62 mukims
('kota-bharu','aur-duri','Aur Duri','mukim'),('kota-bharu','badak-mati','Badak Mati','mukim'),
('kota-bharu','badak','Badak','mukim'),('kota-bharu','banggol','Banggol','mukim'),
('kota-bharu','bechah-mulong','Bechah Mulong','mukim'),('kota-bharu','biah','Biah','mukim'),
('kota-bharu','binjai','Binjai','mukim'),('kota-bharu','buloh-poh','Buloh Poh','mukim'),
('kota-bharu','but','But','mukim'),('kota-bharu','chekli','Chekli','mukim'),
('kota-bharu','chekok','Chekok','mukim'),('kota-bharu','che-latiff','Che Latiff','mukim'),
('kota-bharu','chicha','Chicha','mukim'),('kota-bharu','dal','Dal','mukim'),
('kota-bharu','duson-rendah','Duson Rendah','mukim'),('kota-bharu','jelutong','Jelutong','mukim'),
('kota-bharu','karang','Karang','mukim'),('kota-bharu','kampung-sireh','Kampung Sireh','mukim'),
('kota-bharu','kedai-buloh','Kedai Buloh','mukim'),('kota-bharu','kemubu','Kemubu','mukim'),
('kota-bharu','kenali','Kenali','mukim'),('kota-bharu','ketereh-barat','Ketereh Barat','mukim'),
('kota-bharu','ketereh-timor','Ketereh Timor','mukim'),('kota-bharu','koh','Koh','mukim'),
('kota-bharu','lembu','Lembu','mukim'),('kota-bharu','lubok-jambu','Lubok Jambu','mukim'),
('kota-bharu','lubok-pukol','Lubok Pukol','mukim'),('kota-bharu','lundang-paku','Lundang Paku','mukim'),
('kota-bharu','mahang-barat','Mahang Barat','mukim'),('kota-bharu','mahang-timor','Mahang Timor','mukim'),
('kota-bharu','padang-bongor','Padang Bongor','mukim'),('kota-bharu','padang-enggang','Padang Enggang','mukim'),
('kota-bharu','padang-garong','Padang Garong','mukim'),('kota-bharu','padang-leban','Padang Leban','mukim'),
('kota-bharu','padang-raja','Padang Raja','mukim'),('kota-bharu','padang-sakar','Padang Sakar','mukim'),
('kota-bharu','padang-tengah','Padang Tengah','mukim'),('kota-bharu','panchor','Panchor','mukim'),
('kota-bharu','pangkal-pisang','Pangkal Pisang','mukim'),('kota-bharu','parit','Parit','mukim'),
('kota-bharu','pasir-ha','Pasir Ha','mukim'),('kota-bharu','pasir-mas','Pasir Mas','mukim'),
('kota-bharu','patek','Patek','mukim'),('kota-bharu','pauh','Pauh','mukim'),
('kota-bharu','paya','Paya','mukim'),('kota-bharu','pintu-gang','Pintu Gang','mukim'),
('kota-bharu','pulau','Pulau','mukim'),('kota-bharu','pulau-belanga','Pulau Belanga','mukim'),
('kota-bharu','pulau-gajah','Pulau Gajah','mukim'),('kota-bharu','pulau-panjang','Pulau Panjang','mukim'),
('kota-bharu','pulau-pisang','Pulau Pisang','mukim'),('kota-bharu','sabak','Sabak','mukim'),
('kota-bharu','semut-api','Semut Api','mukim'),('kota-bharu','seterpa','Seterpa','mukim'),
('kota-bharu','tanjong-chat','Tanjong Chat','mukim'),('kota-bharu','tapang','Tapang','mukim'),
('kota-bharu','tebing-tinggi','Tebing Tinggi','mukim'),('kota-bharu','telok','Telok','mukim'),
('kota-bharu','telok-bharu','Telok Bharu','mukim'),('kota-bharu','telok-kitang','Telok Kitang','mukim'),
('kota-bharu','tok-ku','Tok Ku','mukim'),('kota-bharu','wakaf-siku','Wakaf Siku','mukim'),
# Bachok 7 mukims
('bachok','gajah-mati','Gajah Mati','mukim'),('bachok','kuchelong','Kuchelong','mukim'),
('bachok','paya-mengkuang','Paya Mengkuang','mukim'),('bachok','tanjong-jering','Tanjong Jering','mukim'),
('bachok','tanjong-pauh','Tanjong Pauh','mukim'),('bachok','temu-ranggas','Temu Ranggas','mukim'),
('bachok','tualang-salak','Tualang Salak','mukim'),
# Pasir Mas 2 mukims
('pasir-mas','apa-apa','Apa-Apa','mukim'),('pasir-mas','kuala-kelar','Kuala Kelar','mukim'),
# Pasir Puteh 3 mukims
('pasir-puteh','gong-chapa','Gong Chapa','mukim'),('pasir-puteh','gong-pachat','Gong Pachat','mukim'),
('pasir-puteh','pengkalan','Pengkalan','mukim'),
# Tumpat 1 mukim
('tumpat','wakaf-delima','Wakaf Delima','mukim'),
# Lojing 2 mukims
('lojing','balar','Balar','mukim'),('lojing','sigar','Sigar','mukim'),
# 4 pekans
('pasir-mas','pekan-rantau-panjang','Pekan Rantau Panjang','pekan'),
('machang','pekan-temangan','Pekan Temangan','pekan'),
('pasir-puteh','pekan-selising','Pekan Selising','pekan'),
('bachok','jelawat','Jelawat','pekan'),
]
assert len(ADDS) == 81, len(ADDS)

RETYPES = {
 f'{K}:bachok:bandar-bachok': 'bandar',
 f'{K}:tumpat:bandar-tumpat': 'bandar',
 f'{K}:pasir-puteh:bandar-pasir-puteh': 'bandar',
 f'{K}:kuala-krai:bandar-kuala-krai': 'bandar',
 f'{K}:machang:bandar-machang': 'bandar',
 f'{K}:gua-musang:bandar-gua-musang': 'bandar',
 f'{K}:tanah-merah:bandar-tanah-merah': 'bandar',
 f'{K}:tanah-merah:tanah-merah': 'mukim',
}

DELETE_ROW = f'{K}:pasir-mas:bandar-pasir-mas'

# (postcode, old_area, new_area) repoints; all keep flags
REPOINTS = [(pc, f'{K}:pasir-mas:bandar-pasir-mas', f'{K}:pasir-mas:pasir-mas')
            for pc in ['17000','17007','17009','17010','17020','17030','17040','17050','17060','17070']]
REPOINTS += [
 ('17200', f'{K}:pasir-mas:rantau-panjang', f'{K}:pasir-mas:pekan-rantau-panjang'),
 ('18400', f'{K}:machang:temangan', f'{K}:machang:pekan-temangan'),
 ('16810', f'{K}:pasir-puteh:selising', f'{K}:pasir-puteh:pekan-selising'),
 ('16070', f'{K}:bachok:bandar-bachok', f'{K}:bachok:jelawat'),
]

# redundant secondaries to drop (same area holds primary after merge)
DROP_LINKS = [(pc, f'{K}:pasir-mas:pasir-mas', 'false') for pc in ['17000','17007','17009']]

