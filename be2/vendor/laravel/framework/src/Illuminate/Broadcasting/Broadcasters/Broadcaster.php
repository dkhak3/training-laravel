i              �4         , e              �4         � h              �4         � c              �4         � g              �4         b f              �4         0 ^              �4         � `              �4         � _              �4         p	 a              �4         4
 c              �4         �
 b              �4         � \              �4         | _              �4         < _              �4         � _              �4         � _              �4         | ]              �4         8 c              �4           b              �4         � c              �4         � ^              �4         L a              �4          _              �4         � a              �4         � g              �4         d i              �4         8 h              �4         
 a              �4         � c              �4         � b              �4         \ f              �4         * f              �4         � f              5         � b              5         � e              
5         X e              5         $ c              5         � _              5         �  b              5         r! ]              5         ." a              5         �" `               5         �# m              !5         �$ m              "5         l% m              #5         H& m              $5         $' m              %5          ( m              &5         �( m              '5         �) m              (5         �* m              )5         p+ m              *5         L, m              +5         (- m              ,5         . m              -5         �. m              .5         �/ m              /5         �0 m              05         t1 m              15         P2 m              25         ,3 m              35         4 m              45         �4 m              55         �5 m              65         �6 m              75         x7 m              85         T8 m              95         09 m              :5         : m              ;5         �: m              <5         �; m              =5         �< m              >5         |= m              ?5         X> m              @5         4? m              A5         @ m              B5         �@ m              C5         �A m              D5         �B m              E5         �C m              F5         \D 3              K5         �D <              Z5  �       >E 5              96         �E 5              A6         F 5              I6         �F 5              Q6  �       �F 4              7  1       XG 4              C7  �       �G 4              *8  �       ,H 4              �8  �       �H 4              �9  �        I 4              V:  v       jI 4              �:         �I 5              �:         @J 5              �:         �J 5              �:         K 5              �:         �K 5              �:         �K 5              �:         \L 5              ;        �L 5              ;        4M �              8;         dN 5              9;        �N 5              P;        <O �              g;        LP 5              ~;  =      �P 5              �;        $Q 5              �;  =      �Q 5              <        �Q 5              &<        hR 5              =<        �R 5              U<        @S 5              m<        �S 5              �<        T 5              �<        �T 5              �<        �T 5                    ��   
   @       ��      ��   "   ��   *   ��   2   ��   :   ��	   B   ��
   J   ��   R   H    Z   ��   b   ��   j   @    r   ��   z   ��   �   ��   �   ��   �   ��   �   ��   �   ��   �   ��   �   ��   �   ��   �   ��   �   ��   �   ��   �   ��xtractAuthParameters($pattern, $channel, $callback)
    {
        $callbackParameters = $this->extractParameters($callback);

        return collect($this->extractChannelKeys($pattern, $channel))->reject(function ($value, $key) {
            return is_numeric($key);
        })->map(function ($value, $key) use ($callbackParameters) {
            return $this->resolveBinding($key, $value, $callbackParameters);
        })->values()->all();
    }

    /**
     * Extracts the parameters out of what the user passed to handle the channel authentication.
     *
     * @param  callable|string  $callback
     * @return \ReflectionParameter[]
     *
     * @throws \Exception
     */
    protected function extractParameters($callback)
    {
        if (is_callable($callback)) {
            return (new ReflectionFunction($callback))->getParameters();
        } elseif (is_string($callback)) {
            return $this->extractParametersFromClass($callback);
        }

        throw new Exception('Given channel handler is an unknown type.');
    }

    /**
     * Extracts the parameters out of a class channel's "join" method.
     *
     * @param  string  $callback
     * @return \ReflectionParameter[]
     *
     * @throws \Exception
     */
    protected function extractParametersFromClass($callback)
    {
        $reflection = new ReflectionClass($callback);

        if (! $reflection->hasMethod('join')) {
            throw new Exception('Class based channel must define a "join" method.');
        }

        return $reflection->getMethod('join')->getParameters();
    }

    /**
     * Extract the channel keys from the incoming channel name.
     *
     * @param  string  $pattern
     * @param  string  $channel
     * @return array
     */
    protected function extractChannelKeys($pattern, $channel)
    {
        preg_match('/^'.preg_replace('/\{(.*?)\}/', '(?<$1>[^\.]+)', $pattern).'/', $channel, $keys);

        return $keys;
    }

    /**
     * Resolve the given parameter binding.
     *
     * @param  string  $key
     * @param  string  $value
     * @param  array  $callbackParameters
     * @return mixed
     */
    protected function resolveBinding($key, $value, $callbackParameters)
    {
        $newValue = $this->resolveExplicitBindingIfPossible($key, $value);

        return $newValue === $value ? $this->resolveImplicitBindingIfPossible(
            $key, $value, $callbackParameters
        ) : $newValue;
    }

    /**
     * Resolve an explicit parameter binding if applicable.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function resolveExplicitBindingIfPossible($key, $value)
    {
        $binder = $this->binder();

        if ($binder && $binder->getBindingCallback($key)) {
            return call_user_func($binder->getBindingCallback($key), $value);
        }

        return $value;
    }

    /**
     * Resolve an implicit parameter binding if applicable.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $callbackParameters
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    protected function resolveImplicitBindingIfPossible($key, $value, $callbackParameters)
    {
        foreach ($callbackParameters as $parameter) {
            if (! $this->isImplicitlyBindable($key, $parameter)) {
                continue;
            }

            $className = Reflector::getParameterClassName($parameter);

            if (is_null($model = (new $className)->resolveRouteBinding($value))) {
                throw new AccessDeniedHttpException;
            }

            return $model;
        }

        return $value;
    }

    /**
     * Determine if a given key and parameter is implicitly bindable.
     *
     * @param  string  $key
     * @param  \ReflectionParameter  $parameter
     * @return bool
     */
    protected function isImplicitlyBindable($key, $parameter)
    {
        return $parameter->getName() === $key &&
                        Reflector::isParameterSubclassOf($parameter, UrlRoutable::class);
    }

    /**
     * Format the channel array into an array of strings.
     *
     * @param  array  $channels
     * @return array
     */
    protected function formatChannels(array $channels)
    {
        return array_map(function ($channel) {
            return (string) $channel;
        }, $channels);
    }

    /**
     * Get the model binding registrar instance.
     *
     * @return \Illuminate\Contracts\Routing\BindingRegistrar
     */
    protected function binder()
    {
        if (! $this->bindingRegistrar) {
            $this->bindingRegistrar = Container::getInstance()->bound(BindingRegistrar::class)
                        ? Container::getInstance()->make(BindingRegistrar::class) : null;
        }

        return $this->bindingRegistrar;
    }

    /**
     * Normalize the given callback into a callable.
     *
     * @param  mixed  $callback
     * @return callable
     */
    protected function normalizeChannelHandlerToCallable($callback)
    {
        return is_callable($callback) ? $callback : function (...$args) use ($callback) {
            return Container::getInstance()
                ->make($callback)
                ->join(...$args);
        };
    }

    /**
     * Retrieve the authenticated user using the configured guard (if any).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $channel
     * @return mixed
     */
    protected function retrieveUser($request, $channel)
    {
        $options = $this->retrieveChannelOptions($channel);

        $guards = $options['guards'] ?? null;

        if (is_null($guards)) {
            return $request->user();
        }

        foreach (Arr::wrap($guards) as $guard) {
            if ($user = $request->user($guard)) {
                return $user;
            }
        }
    }

    /**
     * Retrieve options for a certain channel.
     *
     * @param  string  $channel
     * @return array
     */
    protected function retrieveChannelOptions($channel)
    {
        foreach ($this->channelOptions as $pattern => $options) {
            if (! $this->channelNameMatchesPattern($channel, $pattern)) {
                continue;
            }

            return $options;
        }

        return [];
    }

    /**
     * Check if the channel name from the request matches a pattern from registered channels.
     *
     * @param  string  $channel
     * @param  string  $pattern
     * @return bool
     */
    protected function channelNameMatchesPattern($channel, $pattern)
    {
        return preg_match('/^'.preg_replace('/\{(.*?)\}/', '([^\.]+)', $pattern).'$/', $channel);
    }

    /**
     * Get all of the registered channels.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getChannels()
    {
        return collect($this->channels);
    }
}
