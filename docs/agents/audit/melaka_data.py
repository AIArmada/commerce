K = 'my:subdistrict:district:melaka'
D = 'my:district:melaka'
ADDS = [
 ('melaka-tengah','padang-semabok','Padang Semabok','mukim'),
 ('melaka-tengah','bandar-bukit-baru','Bandar Bukit Baru','bandar'),
 ('melaka-tengah','pekan-ayer-molek','Pekan Ayer Molek','pekan'),
 ('melaka-tengah','pekan-batu-berendam','Pekan Batu Berendam','pekan'),
 ('melaka-tengah','pekan-bukit-rambai','Pekan Bukit Rambai','pekan'),
 ('melaka-tengah','pekan-kandang','Pekan Kandang','pekan'),
 ('melaka-tengah','klebang','Klebang','pekan'),
 ('melaka-tengah','pekan-paya-rumput','Pekan Paya Rumput','pekan'),
 ('melaka-tengah','pekan-sungai-udang','Pekan Sungai Udang','pekan'),
 ('melaka-tengah','pekan-tangga-batu','Pekan Tangga Batu','pekan'),
 ('melaka-tengah','pekan-tanjong-kling','Pekan Tanjong Kling','pekan'),
 ('jasin','bandar-merlimau','Bandar Merlimau','bandar'),
 ('jasin','pekan-batang-malaka','Pekan Batang Malaka','pekan'),
 ('jasin','pekan-chin-chin','Pekan Chin Chin','pekan'),
 ('jasin','kesang-pajak','Kesang Pajak','pekan'),
 ('jasin','pekan-nyalas','Pekan Nyalas','pekan'),
 ('jasin','pekan-selandar','Pekan Selandar','pekan'),
 ('jasin','sempang-bekoh','Sempang Bekoh','pekan'),
 ('jasin','pekan-sungai-rambai','Pekan Sungai Rambai','pekan'),
 ('alor-gajah','bandar-masjid-tanah','Bandar Masjid Tanah','bandar'),
 ('alor-gajah','bandar-pulau-sebang','Bandar Pulau Sebang','bandar'),
 ('alor-gajah','pekan-durian-tunggal','Pekan Durian Tunggal','pekan'),
 ('alor-gajah','pekan-kuala-sungai-baru','Pekan Kuala Sungai Baru','pekan'),
 ('alor-gajah','pekan-rembia','Pekan Rembia','pekan'),
]
RETYPES = {
 f'{K}:alor-gajah:lubok-china': 'pekan',
}
DELETE_ROWS = []
DELETE_ROW = None
REPOINTS = [
 ('76400', f'{K}:melaka-tengah:tanjong-kling', f'{K}:melaka-tengah:pekan-tanjong-kling'),
 ('76409', f'{K}:melaka-tengah:tanjong-kling', f'{K}:melaka-tengah:pekan-tanjong-kling'),
 ('77300', f'{K}:jasin:merlimau', f'{K}:jasin:bandar-merlimau'),
 ('77309', f'{K}:jasin:merlimau', f'{K}:jasin:bandar-merlimau'),
 ('77500', f'{K}:jasin:selandar', f'{K}:jasin:pekan-selandar'),
 ('78300', f'{K}:alor-gajah:masjid-tanah', f'{K}:alor-gajah:bandar-masjid-tanah'),
 ('78307', f'{K}:alor-gajah:masjid-tanah', f'{K}:alor-gajah:bandar-masjid-tanah'),
 ('78309', f'{K}:alor-gajah:masjid-tanah', f'{K}:alor-gajah:bandar-masjid-tanah'),
 ('76100', f'{K}:alor-gajah:durian-tunggal', f'{K}:alor-gajah:pekan-durian-tunggal'),
 ('76109', f'{K}:alor-gajah:durian-tunggal', f'{K}:alor-gajah:pekan-durian-tunggal'),
 ('78200', f'{K}:alor-gajah:kuala-sungai-baru', f'{K}:alor-gajah:pekan-kuala-sungai-baru'),
 ('76300', f'{K}:melaka-tengah:sungai-udang', f'{K}:melaka-tengah:pekan-sungai-udang'),
 ('77400', f'{K}:jasin:sungai-rambai', f'{K}:jasin:pekan-sungai-rambai'),
]
ADD_LINKS = [
 # covering-mukim secondaries on the moved main town codes
 ('76400', f'{K}:melaka-tengah:tanjong-kling', 'served_by', 'false'),
 ('77300', f'{K}:jasin:merlimau', 'served_by', 'false'),
 ('77500', f'{K}:jasin:selandar', 'served_by', 'false'),
 ('78300', f'{K}:alor-gajah:masjid-tanah', 'served_by', 'false'),
 ('76100', f'{K}:alor-gajah:durian-tunggal', 'served_by', 'false'),
 ('78200', f'{K}:alor-gajah:kuala-sungai-baru', 'served_by', 'false'),
 ('76300', f'{K}:melaka-tengah:sungai-udang', 'served_by', 'false'),
 ('77400', f'{K}:jasin:sungai-rambai', 'served_by', 'false'),
 # suburb-town secondaries (v-swiss: locality stays Melaka/Jasin/Asahan/Alor Gajah)
 ('75050', f'{K}:melaka-tengah:padang-semabok', 'served_by', 'false'),
 ('75150', f'{K}:melaka-tengah:bandar-bukit-baru', 'served_by', 'false'),
 ('75200', f'{K}:melaka-tengah:klebang', 'served_by', 'false'),
 ('75260', f'{K}:melaka-tengah:pekan-bukit-rambai', 'served_by', 'false'),
 ('75350', f'{K}:melaka-tengah:pekan-batu-berendam', 'served_by', 'false'),
 ('75460', f'{K}:melaka-tengah:pekan-ayer-molek', 'served_by', 'false'),
 ('75460', f'{K}:melaka-tengah:pekan-kandang', 'served_by', 'false'),
 ('76450', f'{K}:melaka-tengah:pekan-paya-rumput', 'served_by', 'false'),
 ('76400', f'{K}:melaka-tengah:pekan-tangga-batu', 'served_by', 'false'),
 ('77000', f'{K}:jasin:kesang-pajak', 'served_by', 'false'),
 ('77000', f'{K}:jasin:pekan-chin-chin', 'served_by', 'false'),
 ('77100', f'{K}:jasin:pekan-nyalas', 'served_by', 'false'),
 ('78000', f'{K}:alor-gajah:pekan-rembia', 'served_by', 'false'),
 ('73000', f'{K}:alor-gajah:bandar-pulau-sebang', 'served_by', 'false'),
]
DROP_LINKS = []
