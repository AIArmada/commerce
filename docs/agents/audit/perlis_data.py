K = 'my:subdistrict:state'
D = 'my:state'
# Perlis: TIADA DAERAH — subdistricts hang directly under the state.
# Book gazettes Bandar Arau (40), Bandar Kangar (41), Pekan Kuala
# Perlis (70), Pekan Kaki Bukit (72); all 22 mukims already rowed.
ADDS = [
 ('perlis','bandar-arau','Bandar Arau','bandar'),
 ('perlis','pekan-kuala-perlis','Pekan Kuala Perlis','pekan'),
]
RETYPES = {
 'my:subdistrict:state:perlis:kangar': 'bandar',
 'my:subdistrict:state:perlis:kaki-bukit': 'pekan',
}
DELETE_ROWS = []
DROP_LINKS = []
REPOINTS = [
 ('02600','my:subdistrict:state:perlis:arau','my:subdistrict:state:perlis:bandar-arau'),
 ('02607','my:subdistrict:state:perlis:arau','my:subdistrict:state:perlis:bandar-arau'),
 ('02609','my:subdistrict:state:perlis:arau','my:subdistrict:state:perlis:bandar-arau'),
 ('02000','my:subdistrict:state:perlis:kuala-perlis','my:subdistrict:state:perlis:pekan-kuala-perlis'),
]
ADD_LINKS = [
 ('02600','my:subdistrict:state:perlis:arau','served_by','false'),
 ('02607','my:subdistrict:state:perlis:arau','served_by','false'),
 ('02609','my:subdistrict:state:perlis:arau','served_by','false'),
 ('02000','my:subdistrict:state:perlis:kuala-perlis','served_by','false'),
]
