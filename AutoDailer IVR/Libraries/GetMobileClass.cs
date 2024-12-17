using Newtonsoft.Json.Linq;
using System.Diagnostics;

public class GetMobileClass
    {
    public string GetMobile(string GetDataResponse, int index)
        {

        var arr = JArray.Parse(GetDataResponse);

        var data = arr[index]; //JObject.Parse(arr[index]);
        return (string)data["mobile"];
        }
    }


