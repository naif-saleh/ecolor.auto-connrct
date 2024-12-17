using Newtonsoft.Json.Linq;
using System.Diagnostics;

public class GetProviderClass
    {
    public string GetProvider(string GetDataResponse, int index)
        {

        var arr = JArray.Parse(GetDataResponse);

        var data = arr[index]; //JObject.Parse(arr[index]);
        return (string)data["extension"];
        }
    }


