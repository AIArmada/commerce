K = 'my:subdistrict:district:perak'
D = 'my:district:perak'
ADDS = []
RETYPES = {}
# Tronoh consolidation: the UPI book gazettes Mukim + Bandar Tronoh under
# Kinta (WK.3747/29.09.2022; Bandar Tronoh code 56 in the Kinta section),
# so the Sept kampar:tronoh postal locality is a wrong-district duplicate.
# 31750 primary moves to Bandar Tronoh; the mukim gets a covering secondary.
DELETE_ROWS = [f'{K}:kampar:tronoh']
DROP_LINKS = []
REPOINTS = [('31750', f'{K}:kampar:tronoh', f'{K}:kinta:bandar-tronoh')]
ADD_LINKS = [
 ('31750', f'{K}:kinta:tronoh', 'served_by', 'false'),
]
