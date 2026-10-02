K = 'my:subdistrict:district:pahang'
D = 'my:district:pahang'
ADDS = [
 ('bentong','bandar-bentong','Bandar Bentong','bandar'),
 ('bentong','telemung','Telemung','pekan'),
 ('cameron-highlands','bandar-tanah-rata','Bandar Tanah Rata','bandar'),
 ('cameron-highlands','lubok-tamang','Lubok Tamang','pekan'),
 ('cameron-highlands','pekan-ringlet','Pekan Ringlet','pekan'),
 ('jerantut','pekan-kuala-tembeling','Pekan Kuala Tembeling','pekan'),
 ('jerantut','jeransang','Jeransang','pekan'),
 ('kuantan','pekan-beserah','Pekan Beserah','pekan'),
 ('kuantan','tanjung-lumpur','Tanjung Lumpur','pekan'),
 ('lipis','bandar-kuala-lipis','Bandar Kuala Lipis','bandar'),
 ('pekan','bandar-pekan','Bandar Pekan','bandar'),
 ('pekan','pekan-kuala-pahang','Pekan Kuala Pahang','pekan'),
 ('pekan','nenasi','Nenasi','pekan'),
 ('raub','pekan-raub','Pekan Raub','pekan'),
 ('raub','pekan-dong','Pekan Dong','pekan'),
 ('raub','pekan-tras','Pekan Tras','pekan'),
 ('raub','cheroh','Cheroh','pekan'),
 ('raub','sang-lee','Sang Lee','pekan'),
 ('raub','sungai-ruan','Sungai Ruan','pekan'),
 ('raub','sungai-kelau','Sungai Kelau','pekan'),
 ('temerloh','bandar-mentakab','Bandar Mentakab','bandar'),
 ('temerloh','pekan-kerdau','Pekan Kerdau','pekan'),
 ('rompin','baharu-rompin','Baharu Rompin','bandar'),
 ('rompin','rompin-i','Rompin I','bandar'),
 ('rompin','rompin-ii','Rompin II','bandar'),
 ('rompin','rompin-iii','Rompin III','bandar'),
 ('rompin','rompin-iv','Rompin IV','bandar'),
 ('rompin','bandar-pontian','Bandar Pontian','bandar'),
 ('rompin','bandar-endau','Bandar Endau','bandar'),
 ('rompin','bandar-tioman','Bandar Tioman','bandar'),
 ('rompin','pekan-tioman','Pekan Tioman','pekan'),
 ('maran','pekan-chenor','Pekan Chenor','pekan'),
 ('maran','sri-jaya','Sri Jaya','pekan'),
 ('bera','bandar-triang','Bandar Triang','bandar'),
 ('bera','durian-tawar','Durian Tawar','pekan'),
 ('bera','mengkuang','Mengkuang','pekan'),
]
RETYPES = {
 f'{K}:kuantan:gambang': 'bandar',
 f'{K}:lipis:benta': 'pekan',
 f'{K}:lipis:padang-tengku': 'pekan',
 f'{K}:bera:mengkarak': 'pekan',
}
DELETE_ROW = None
REPOINTS = (
 [(pc, f'{K}:cameron-highlands:tanah-rata', f'{K}:cameron-highlands:bandar-tanah-rata') for pc in ['39000','39007','39009','39010']] +
 [('39200', f'{K}:cameron-highlands:ringlet', f'{K}:cameron-highlands:pekan-ringlet')] +
 [(pc, f'{K}:pekan:pekan', f'{K}:pekan:bandar-pekan') for pc in ['26600','26607','26609','26610','26620','26630','26640','26650','26660','26680']] +
 [(pc, f'{K}:temerloh:mentakab', f'{K}:temerloh:bandar-mentakab') for pc in ['28400','28407','28409']] +
 [('28100', f'{K}:maran:chenor', f'{K}:maran:pekan-chenor')] +
 [(pc, f'{K}:bera:triang', f'{K}:bera:bandar-triang') for pc in ['28300','28310','28320','28330','28350']] +
 [('27400', f'{K}:raub:dong', f'{K}:raub:pekan-dong')] +
 [('27500', f'{D}:raub', f'{K}:raub:sungai-ruan')] +
 [(pc, f'{K}:bentong:bentong', f'{K}:bentong:bandar-bentong') for pc in ['28700','28707','28709','28730','28740','28750']] +
 [('26100', f'{K}:kuantan:sungai-karang', f'{K}:kuantan:pekan-beserah')]
)
DROP_LINKS = []
