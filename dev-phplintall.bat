echo checking php using php -l
echo php files error check report %date% %time%  >dev-output.txt
echo
php -v  >>dev-output.txt
echo
for /r %%i in (*.php) do ( echo.  & php -l "%%i"  )  >>dev-output.txt
start "" "dev-output.txt" win