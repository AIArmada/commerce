K = 'my:subdistrict:district:perak'
D = 'my:district:perak'
ADDS = []
RETYPES = {}
# Trong consolidation: the book gazettes only Mukim + Pekan Terung under
# Larut-Matang (codes 10/74); the Sept pass already judged Trong the same
# place as Terung. The locality's 34800 secondary is redundant with
# Pekan Terung's primary and the mukim's secondary, so both go.
DELETE_ROWS = [f'{K}:larut-matang:trong']
DROP_LINKS = [('34800', f'{K}:larut-matang:trong', 'false')]
REPOINTS = []
ADD_LINKS = []
