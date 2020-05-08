# blesta-support-pin

## Features
- Configurable length PIN
- Optional PIN expiration/rotation
- Client & admin widget

![Client Widget Screenshot](docs/client_widget.png?raw=true "Client Widget Screenshot")

## API
### Validate PIN for a given client
#### Endpoint
`<blesta_url>/api/SupportPin.ClientPin/isValid.json"`

#### Parameters
| Parameter  | Description          | Example |  
|------------|----------------------|---------|
| client\_id | ID of client account | 7       |  
| client\_no | Client number        | 1500    |  
| pin        | User-supplied PIN    | 90120   |  

Only supply one of `client_id` _or_ `client_no`.

`pin` must always be supplied.
 
#### Response
Returns a boolean value - true if the PIN is a match, false otherwise

```
{"response":false}
```

