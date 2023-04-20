om,zeleb.es,mamiejeanne.news,digitalnews365.com,genialne.pl,weltfussball.de,astrology.com,janamtv.com,java67.com,kizlarsoruyor.com,thereisnews.com,gossip-room.fr,histoire-pour-tous.fr,nordpresse.be,kobieceinspiracje.pl,niefart.pl,stylunio.pl,daily.lessonslearnedinlife.com,arreunicornio.es,cinema.jeuxactu.com,newstab.us,podaj.to,positivr.fr,howinteresting.net,uokhun.uk,humanityworld.me,storyandco.fr,unnuetzes.com,fussballfieber.de,nationmultimedia.com,sologossip.it,texashillcountry.com,wikitree.co.kr,youreduaction.it,lady.mk,urbanplayer.hu,indianexpress.com,financialexpress.com,loksatta.com,jansatta.com,inuth.com,game-debate.com,viva.ro,sm3ha.com,dirtbike.ro,ebihoreanul.ro,larissanet.gr,pillowfights.gr,e-dimosio.gr,ekran.mk,tothemaonline.com,echoroukonline.com,casa.acasa.ro,talentabout.gr,foititikanea.gr,mother.gr,dicasdemulher.com.br,elimparcial.com,lacronica.com,commentimemorabili.it,superanimes.site,tvonline.plus,subtitlesplus.com,vtube.pro,dcnepal.com,mzamin.com,popularne.pl,makorrishon.co.il,teteamodeler.com,diariogol.com,economiadigital.es,news.com.au,portal.tds.net,beachraider.com,dasibogilink.com,rosario3.com,novo.folhavitoria.com.br,ambito.com,fatosdesconhecidos.com.br,indiacelebrating.com,klickaud.com,trucs-et-astuces.co,statoquotidiano.it,24.sapo.pt,animeplus.org,armstrongmywire.com,muyinteresante.es,bold.dk,filmehd.net,microsiervos.com,cerodosbe.com,offsite.com.cy,blinker.de,st-georg.de,trendszilla.net,beziehungsweise-magazin.de,totalprosports.com,biz-journal.jp,classiccountrymusic.com,dailyrockbox.com,monse.club,ehumor.pl,diy-bastelideen.com,apsari.com,mundohispanico.com,info7.mx,agrarszektor.hu,smartcompany.com.au,wideopeneats.com,receiteria.com.br,somosmamas.com.ar,pointsmeals.com,forbes.com.mx,spysparrow.me,efesalud.com,tipps-zum-reisen.de,seriesmetro.com,huffingtonpost.in,gotoknow.org,melty.fr,techblog.gr,evianews.com,buzzfeednews.com,diziizlesen1.com,nezzsorozatokat.info,botapress.info,turnulsfatului.ro,glamour.ro,psychologies.ro,rotana.net,greece10best.com,insajderi.com,newsbomb.com.cy,playdome.hu,ziarulunirea.ro,gsh.al,buzzfeed.com,delicieux.fr,feed.betterbythemin.com,portfolio.hu,penzcentrum.hu,infostart.hu,stirinebune.gsp.ro,oroskopio.org,newsteam.ro,magyarhirlap.hu,sayat.me,noizz.ro,filmaon.org,this-is-italy.com,stoxos.gr,mala3eb.com,to10.gr,comisarul.ro,elle.ro,epochtimes.de,wetter.com,wohnfantasien.de,medicaregranny.com,tsa-algerie.com,pluralist.com,apertura.com,debate.com.mx,pcworld.pl,mybinoo.com,nigeriaworld.com,militarybud.com,psychicmonday.com,tummytuckhipo.com,pourquoidocteur.fr,qooqootv.pro,factaholics.com,wetter.net,utopia.de,worldtravelling.com,brocabrac.fr,forocomunista.com,siamsport.co.th,weeklyhoroscope.com,tnews.co.th,123telugu.com,opiaces-tpe.e-monsite.com,cafedeclic.com,drama3s.to,joorala.com,tvtamilshows.net,mkvcage.ws,cutestat.com,timesunion.com,newstimes.com,nydailynews.com,boston25news.com,indiatoday.in,thaivisa.com,newscentermaine.com,wwltv.com,thecut.com,intoupload.net,finanzen.net,bannedbook.org,kontrokultura.it,watchmecraft.com,horoscopovirtual.com.br,9tv.co.il,gartendialog.de,hausgarten.net,talu.de,thehollywoodconservative.us,slydor.com,health06.com,kanwatch.online,frontera.info,japantimes.co.jp,bitchyf.it,termometropolitico.it,juvelive.it,systemed.fr,alwatanvoice.com,mysanantonio.com,alaan.tv,new.el-ahly.com,akhbaralaan.net,babnet.net,akhbarelyaom.com,ibelieveinsci.com,liilas.com,kora11.com,wazaef4u.net,businessweekme.com,raseef5.com,egyweb.space,3ayezakol.com,yawmek.com,big14me.com,kapook.com,ckm.pl,harpersbazaar.pl,joy.pl,kozaczek.pl,shape.pl,supermamy.pl,zeberka.pl,papilot.pl,slate.com,seloger.com,sanook.com,misspennystocks.com,healthygeorge.com,tradingblvd.com,loanpride.com,therapyjoker.com,gameofglam.com,investmentguru.com,financeblvd.com,medicalmatters.com,directhealthy.com,financenancy.com,mortgageafterlife.com,macclesfield-live.co.uk,eonline.com,rsvplive.ie,cornwalllive.com,devonlive.com,hulldailymail.co.uk,unilad.co.uk,turtleboysports.com,wooninspiraties.com,handigetips.nl,bluradio.com,fullmeasure.news,3oud.com,alqiyady.com,arabsturbo.com,layalina.com,ra2ej.com,sa     * @param  array  $config
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected function callCustomCreator(array $config)
    {
        return $this->customCreators[$config['driver']]($this->app, $config);
    }

    /**
     * Create an instance of the local driver.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function createLocalDriver(array $config)
    {
        $visibility = PortableVisibilityConverter::fromArray(
            $config['permissions'] ?? [],
            $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE
        );

        $links = ($config['links'] ?? null) === 'skip'
            ? LocalAdapter::SKIP_LINKS
            : LocalAdapter::DISALLOW_LINKS;

        $adapter = new LocalAdapter(
            $config['root'], $visibility, $config['lock'] ?? LOCK_EX, $links
        );

        return new FilesystemAdapter($this->createFlysystem($adapter, $config), $adapter, $config);
    }

    /**
     * Create an instance of the ftp driver.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function createFtpDriver(array $config)
    {
        if (! isset($config['root'])) {
            $config['root'] = '';
        }

        $adapter = new FtpAdapter(FtpConnectionOptions::fromArray($config));

        return new FilesystemAdapter($this->createFlysystem($adapter, $config), $adapter, $config);
    }

    /**
     * Create an instance of the sftp driver.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function createSftpDriver(array $config)
    {
        $provider = SftpConnectionProvider::fromArray($config);

        $root = $config['root'] ?? '/';

        $visibility = PortableVisibilityConverter::fromArray(
            $config['permissions'] ?? []
        );

        $adapter = new SftpAdapter($provider, $root, $visibility);

        return new FilesystemAdapter($this->createFlysystem($adapter, $config), $adapter, $config);
    }

    /**
     * Create an instance of the Amazon S3 driver.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Filesystem\Cloud
     */
    public function createS3Driver(array $config)
    {
        $s3Config = $this->formatS3Config($config);

        $root = (string) ($s3Config['root'] ?? '');

        $visibility = new AwsS3PortableVisibilityConverter(
            $config['visibility'] ?? Visibility::PUBLIC
        );

        $streamReads = $s3Config['stream_reads'] ?? false;

        $client = new S3Client($s3Config);

        $adapter = new S3Adapter($client, $s3Config['bucket'], $root, $visibility, null, $config['options'] ?? [], $streamReads);

        return new AwsS3V3Adapter(
            $this->createFlysystem($adapter, $config), $adapter, $s3Config, $client
        );
    }

    /**
     * Format the given S3 configuration with the default options.
     *
     * @param  array  $config
     * @return array
     */
    protected function formatS3Config(array $config)
    {
        $config += ['version' => 'latest'];

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return Arr::except($config, ['token']);
    }

    /**
     * Create a scoped driver.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function createScopedDriver(array $config)
    {
        if (empty($config['disk'])) {
            throw new InvalidArgumentException('Scoped disk is missing "disk" configuration option.');
        } elseif (empty($config['prefix'])) {
            throw new InvalidArgumentException('Scoped disk is missing "prefix" configuration option.');
        }

        return $this->build(tap(
            $this->getConfig($config['disk']),
            fn (&$parent) => $parent['prefix'] = $config['prefix']
        ));
    }

    /**
     * Create a Flysystem instance with the given adapter.
     *
     * @param  \League\Flysystem\FilesystemAdapter  $adapter
     * @param  array  $config
     * @return \League\Flysystem\FilesystemOperator
     */
    protected function createFlysystem(FlysystemAdapter $adapter, array $config)
    {
        if ($config['read-only'] ?? false === true) {
            $adapter = new ReadOnlyFilesystemAdapter($adapter);
        }

        if (! empty($config['prefix'])) {
            $adapter = new PathPrefixedAdapter($adapter, $config['prefix']);
        }

        return new Flysystem($adapter, Arr::only($config, [
            'directory_visibility',
            'disable_asserts',
            'temporary_url',
            'url',
            'visibility',
        ]));
    }

    /**
     * Set the given disk instance.
     *
     * @param  string  $name
     * @param  mixed  $disk
     * @return $this
     */
    public function set($name, $disk)
    {
        $this->disks[$name] = $disk;

        return $this;
    }

    /**
     * Get the filesystem connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->app['config']["filesystems.disks.{$name}"] ?: [];
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['filesystems.default'];
    }

    /**
     * Get the default cloud driver name.
     *
     * @return string
     */
    public function getDefaultCloudDriver()
    {
        return $this->app['config']['filesystems.cloud'] ?? 's3';
    }

    /**
     * Unset the given disk instances.
     *
     * @param  array|string  $disk
     * @return $this
     */
    public function forgetDisk($disk)
    {
        foreach ((array) $disk as $diskName) {
            unset($this->disks[$diskName]);
        }

        return $this;
    }

    /**
     * Disconnect the given disk and remove from local cache.
     *
     * @param  string|null  $name
     * @return void
     */
    public function purge($name = null)
    {
        $name ??= $this->getDefaultDriver();

        unset($this->disks[$name]);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Set the application instance used by the manager.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return $this
     */
    public function setApplication($app)
    {
        $this->app = $app;

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->disk()->$method(...$parameters);
    }
}
