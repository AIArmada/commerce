K = 'my:subdistrict:district:johor'
D = 'my:district:johor'
ADDS = []
RETYPES = {}
DELETE_ROW = None
REPOINTS = [
 ('85210', f'{K}:segamat:jementah', f'{K}:segamat:bandar-jementah'),
 ('85220', f'{K}:segamat:jementah', f'{K}:segamat:bandar-jementah'),
 ('84710', f'{K}:tangkak:gerisek', f'{K}:tangkak:pekan-grisek'),
]
ADD_LINKS = [
 # covering-mukim secondaries on the moved town codes (Sept rule: covering
 # admin areas stay as secondary links; cf. 86000/Kluang, 86800/Mersing)
 ('82200', f'{K}:pontian:benut', 'served_by', 'false'),
 ('84150', f'{K}:muar:parit-jawa', 'served_by', 'false'),
 ('84160', f'{K}:muar:parit-jawa', 'served_by', 'false'),
 ('86600', f'{K}:kluang:paloh', 'served_by', 'false'),
 ('85200', f'{K}:segamat:jementah', 'served_by', 'false'),
 ('85300', f'{K}:segamat:labis', 'served_by', 'false'),
 ('86500', f'{K}:segamat:bekok', 'served_by', 'false'),
 ('85010', f'{K}:segamat:buloh-kasap', 'served_by', 'false'),
 # 84710 follows the 84700 shape: pekan primary, town locality secondary
 ('84710', f'{K}:tangkak:gerisek', 'served_by', 'false'),
]
DROP_LINKS = []
