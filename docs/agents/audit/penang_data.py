K = 'my:subdistrict:district:pulau-pinang'
D = 'my:district:pulau-pinang'
# Penang: mukims verified (SPT 1-21; SPU 1-14+16, genuine 15 skip;
# SPS 1-16; TL 13-18; BD 1-12+A-J). Three gazetted bandars missing:
# SPS 41 Sungai Bakap, TL 47 Tanjong Tokong, TL 48 Tanjong Pinang.
# 10470 is purely Tokong-area streets: primary moves off George Town.
ADDS = [
 ('seberang-perai-selatan','bandar-sungai-bakap','Bandar Sungai Bakap','bandar'),
 ('timur-laut','tanjong-tokong','Tanjong Tokong','bandar'),
 ('timur-laut','tanjong-pinang','Tanjong Pinang','bandar'),
]
RETYPES = {}
DELETE_ROWS = []
DROP_LINKS = []
REPOINTS = [
 ('10470',f'{K}:timur-laut:bandar-george-town',f'{K}:timur-laut:tanjong-tokong'),
]
ADD_LINKS = [
 ('10470',f'{K}:timur-laut:tanjong-pinang','served_by','false'),
 ('14200',f'{K}:seberang-perai-selatan:bandar-sungai-bakap','served_by','false'),
]
