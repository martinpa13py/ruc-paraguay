# ruc-paraguay
Fetch, search and retrieve RUC codes from the official taxes database from SET. 

Descargar, buscar y brindar información sobre los numeros de RUC desde la fuente oficial del SET en Paraguay.


## Install

Use composer to install the package, just run this comamnd to add it to the composer.json

`composer require martinpa13py/ruc-paraguay`


The autodiscovery option should take care of things, but if that doesnt work follow the next steps.

Add the package to config/app.php to the providers list:

`martinpa13py\RUCParaguay\RUCParaguayServiceProvider::class`

Add the package to config/app.php to the aliases list:

`'RUCParaguay' => martinpa13py\RUCParaguay\Facades\RUCParaguayFacade::class`




## Artisan Commands 

This are the artisan commands avaible, you should hook update the data at least once a month.

`php artisan ruc:update`

Fetchs and updates all the local data from the source. Will download several zip files, decompress to text files and load them into the database.

**Opciones disponibles:**

- `--force` : Fuerza la descarga borrando los archivos en caché antes de descargar (ignora la validación de 48 horas)
- `--batch-size=1000` : Cantidad de registros a insertar por lote (mínimo 100). Útil para optimizar memoria/rendimiento.
- `--limit=N` : Límite máximo de registros a importar. Útil para pruebas rápidas.

**Ejemplos:**

```bash
# Descarga normal (respeta caché de 48h)
php artisan ruc:update

# Forzar descarga completa ignorando caché
php artisan ruc:update --force

# Importar solo 5000 registros (pruebas)
php artisan ruc:update --limit=5000

# Usar lotes de 2000 para mejor rendimiento
php artisan ruc:update --batch-size=2000

# Combinar opciones
php artisan ruc:update --force --batch-size=500 --limit=10000
```

`php artisan ruc:search Castillo Pablo`

Performs a searchs inside the database with the provided information. Will return  results and some debug info.

## HOW TO USE

To search for information inside the database you can especify the fields you are looking for like this:
```
$toSearch=array(
	'nro_ruc' 	=>'4600',
	'denominacion' 	=>'alejandro',
	'ruc_anterior' 	=>'ca',
);
RUCParaguay::search($toSearch);
```

You can also search with only one field:

```
$toSearch=array(
	'nro_ruc' =>'460018',
);
RUCParaguay::search($toSearch);
```

Or you can search the whole thing like this:

```
RUCParaguay::search('46001');
```

To update the database periodically run the artisan command, it should be hooked to a cron command to run every couple of days. The update is hardset inside the code to be able to be executed a maximun of once every 48 hours.


`php artisan ruc:update`


### TODO

- [X] Add code examples.

- [ ] Find a way to make it faster without dumping direct-to-database.