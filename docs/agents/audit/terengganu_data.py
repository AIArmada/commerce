K = 'my:subdistrict:district:terengganu'
D = 'my:district:terengganu'
# Terengganu: Dungun "28 MUKIM KUALA DUNGUN" is a page-number artifact;
# Besut 71 "TRGN" is the gazette prefix (TRGN 507/76), not part of the name.
ADDS = [
 ('besut','pekan-kampung-raja','Pekan Kampung Raja','pekan'),
 ('besut','pekan-kuala-besut','Pekan Kuala Besut','pekan'),
 ('dungun','pekan-kuala-paka','Pekan Kuala Paka','pekan'),
 ('kemaman','mukim-cukai','Mukim Cukai','mukim'),
 ('kemaman','pekan-air-jernih','Pekan Air Jernih','pekan'),
 ('kemaman','pekan-air-putih','Pekan Air Putih','pekan'),
 ('kemaman','pekan-kemasik','Pekan Kemasik','pekan'),
 ('kemaman','pekan-kijal','Pekan Kijal','pekan'),
 ('kuala-terengganu','pekan-cabang-tiga','Pekan Cabang Tiga','pekan'),
 ('hulu-terengganu','pekan-kuala-berang','Pekan Kuala Berang','pekan'),
 ('marang','pekan-bukit-payung','Pekan Bukit Payung','pekan'),
 ('setiu','tasik','Tasik','mukim'),
 ('kuala-nerus','pakoh','Pakoh','mukim'),
]
RETYPES = {}
# Consolidations: Bukit Payong town = Pekan Bukit Payung (21400 moves);
# Ayer Puteh mukim was Pekan Air Putih misclassified (24050 moves).
DELETE_ROWS = [f'{K}:marang:bukit-payong', f'{K}:kemaman:ayer-puteh']
DROP_LINKS = []
REPOINTS = [
 ('22200', f'{K}:besut:kampung-raja', f'{K}:besut:pekan-kampung-raja'),
 ('22300', f'{K}:besut:kuala-besut', f'{K}:besut:pekan-kuala-besut'),
 ('22307', f'{K}:besut:kuala-besut', f'{K}:besut:pekan-kuala-besut'),
 ('22309', f'{K}:besut:kuala-besut', f'{K}:besut:pekan-kuala-besut'),
 ('24200', f'{K}:kemaman:kemasik', f'{K}:kemaman:pekan-kemasik'),
 ('24207', f'{K}:kemaman:kemasik', f'{K}:kemaman:pekan-kemasik'),
 ('24209', f'{K}:kemaman:kemasik', f'{K}:kemaman:pekan-kemasik'),
 ('24210', f'{K}:kemaman:kemasik', f'{K}:kemaman:pekan-kemasik'),
 ('24220', f'{K}:kemaman:kemasik', f'{K}:kemaman:pekan-kemasik'),
 ('24100', f'{K}:kemaman:kijal', f'{K}:kemaman:pekan-kijal'),
 ('24107', f'{K}:kemaman:kijal', f'{K}:kemaman:pekan-kijal'),
 ('24109', f'{K}:kemaman:kijal', f'{K}:kemaman:pekan-kijal'),
 ('21700', f'{K}:hulu-terengganu:kuala-berang', f'{K}:hulu-terengganu:pekan-kuala-berang'),
 ('21400', f'{K}:marang:bukit-payong', f'{K}:marang:pekan-bukit-payung'),
 ('24050', f'{K}:kemaman:ayer-puteh', f'{K}:kemaman:pekan-air-putih'),
]
ADD_LINKS = [
 ('22200', f'{K}:besut:kampung-raja', 'served_by', 'false'),
 ('22300', f'{K}:besut:kuala-besut', 'served_by', 'false'),
 ('22307', f'{K}:besut:kuala-besut', 'served_by', 'false'),
 ('22309', f'{K}:besut:kuala-besut', 'served_by', 'false'),
 ('24200', f'{K}:kemaman:kemasik', 'served_by', 'false'),
 ('24207', f'{K}:kemaman:kemasik', 'served_by', 'false'),
 ('24209', f'{K}:kemaman:kemasik', 'served_by', 'false'),
 ('24210', f'{K}:kemaman:kemasik', 'served_by', 'false'),
 ('24220', f'{K}:kemaman:kemasik', 'served_by', 'false'),
 ('24100', f'{K}:kemaman:kijal', 'served_by', 'false'),
 ('24107', f'{K}:kemaman:kijal', 'served_by', 'false'),
 ('24109', f'{K}:kemaman:kijal', 'served_by', 'false'),
 ('21700', f'{K}:hulu-terengganu:kuala-berang', 'served_by', 'false'),
 ('24000', f'{K}:kemaman:mukim-cukai', 'served_by', 'false'),
 ('24007', f'{K}:kemaman:mukim-cukai', 'served_by', 'false'),
 ('24009', f'{K}:kemaman:mukim-cukai', 'served_by', 'false'),
 ('24010', f'{K}:kemaman:mukim-cukai', 'served_by', 'false'),
 ('24020', f'{K}:kemaman:mukim-cukai', 'served_by', 'false'),
 ('24030', f'{K}:kemaman:mukim-cukai', 'served_by', 'false'),
 ('24040', f'{K}:kemaman:mukim-cukai', 'served_by', 'false'),
 ('23100', f'{K}:dungun:pekan-kuala-paka', 'served_by', 'false'),
]
