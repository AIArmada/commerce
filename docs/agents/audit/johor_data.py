K = 'my:subdistrict:district:johor'
D = 'my:district:johor'
ADDS = [
 ('johor-bahru','bandar-tebrau','Bandar Tebrau','bandar'),
 ('kluang','bandar-paloh','Bandar Paloh','bandar'),
 ('kluang','bandar-rengam','Bandar Rengam','bandar'),
 ('mersing','bandar-jemaluang','Bandar Jemaluang','bandar'),
 ('mersing','mersing-kanan','Mersing Kanan','bandar'),
 ('mersing','bandar-padang-endau','Bandar Padang Endau','bandar'),
 ('muar','bandar-bukit-kepong','Bandar Bukit Kepong','bandar'),
 ('muar','bandar-parit-jawa','Bandar Parit Jawa','bandar'),
 ('pontian','bandar-benut','Bandar Benut','bandar'),
 ('segamat','bandar-bekok','Bandar Bekok','bandar'),
 ('segamat','bandar-buloh-kasap','Bandar Buloh Kasap','bandar'),
 ('segamat','bandar-jementah','Bandar Jementah','bandar'),
 ('segamat','bandar-labis','Bandar Labis','bandar'),
 ('segamat','gemas-bahru','Gemas Bahru','pekan'),
 ('tangkak','bukit-kangkar','Bukit Kangkar','bandar'),
 ('tangkak','parit-bunga','Parit Bunga','bandar'),
 ('tangkak','bandar-serom','Bandar Serom','bandar'),
 ('tangkak','pekan-grisek','Pekan Grisek','pekan'),
]
RETYPES = {
 f'{K}:muar:panchor': 'bandar',
}
DELETE_ROW = f'{K}:segamat:bandar-segamat'
REPOINTS = [
 ('82200', f'{K}:pontian:benut', f'{K}:pontian:bandar-benut'),
 ('84150', f'{K}:muar:parit-jawa', f'{K}:muar:bandar-parit-jawa'),
 ('84160', f'{K}:muar:parit-jawa', f'{K}:muar:bandar-parit-jawa'),
 ('86600', f'{K}:kluang:paloh', f'{K}:kluang:bandar-paloh'),
 ('86300', f'{K}:kluang:renggam', f'{K}:kluang:bandar-rengam'),
 ('85200', f'{K}:segamat:jementah', f'{K}:segamat:bandar-jementah'),
 ('85300', f'{K}:segamat:labis', f'{K}:segamat:bandar-labis'),
 ('86500', f'{K}:segamat:bekok', f'{K}:segamat:bandar-bekok'),
 ('85010', f'{K}:segamat:segamat', f'{K}:segamat:bandar-buloh-kasap'),
 ('84700', f'{K}:tangkak:gerisek', f'{K}:tangkak:pekan-grisek'),
]
ADD_LINKS = [
 # gazetted town takes the primary; the common-spelling locality stays secondary
 ('86300', f'{K}:kluang:renggam', 'served_by', 'false'),
 ('84700', f'{K}:tangkak:gerisek', 'served_by', 'false'),
 # shared-code secondaries on the new rows (primary stays on the code owner)
 ('84600', f'{K}:muar:bandar-bukit-kepong', 'served_by', 'false'),
 ('73400', f'{K}:segamat:gemas-bahru', 'served_by', 'false'),
 ('84000', f'{K}:tangkak:parit-bunga', 'served_by', 'false'),
 ('84400', f'{K}:tangkak:bandar-serom', 'served_by', 'false'),
 ('84400', f'{K}:tangkak:bukit-kangkar', 'served_by', 'false'),
]
DROP_LINKS = []
