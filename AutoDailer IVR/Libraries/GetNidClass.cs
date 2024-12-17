using Newtonsoft.Json.Linq;
using System.Diagnostics;

public class GetNidClass
    {
    public string GetNid(string GetDataResponse, int index)
        {
        var arr = JArray.Parse(GetDataResponse);
        var data = arr[index]; //JJObject.Parse(GetDataResponse[index]);
        return (string)data["id"];
        }
    }


